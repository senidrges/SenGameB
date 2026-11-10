<?php

namespace App\Actions;

use App\Enums\ConversationType;
use App\Enums\GroupEvent;
use App\Enums\GroupRole;
use App\Enums\MessageType;
use App\Events\GroupUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Life of a WhatsApp-like group: creation, members joining and leaving,
 * admins. Every change leaves a grey notice in the chat (a system message)
 * and tells the phones showing the group to refresh it.
 */
class ManageGroup
{
    public function __construct(private SendMessage $sendMessage) {}

    /**
     * The creator becomes the first admin.
     *
     * @param  Collection<int, User>  $members
     */
    public function create(User $creator, string $name, ?string $description, Collection $members, ?string $avatarPath = null): Conversation
    {
        $group = DB::transaction(function () use ($creator, $name, $description, $members, $avatarPath): Conversation {
            $group = Conversation::create([
                'type' => ConversationType::Group,
                'name' => $name,
                'description' => $description,
                'avatar_path' => $avatarPath,
                'created_by' => $creator->id,
            ]);

            $now = now();
            $group->participants()->attach(
                $members->push($creator)->mapWithKeys(fn (User $member) => [$member->id => [
                    'role' => $member->is($creator) ? GroupRole::Admin->value : GroupRole::Member->value,
                    'joined_at' => $now,
                    'history_from' => $now,
                    'last_read_at' => $member->is($creator) ? $now : null,
                ]])->all(),
            );

            return $group;
        });

        $this->notice($group, $creator, GroupEvent::Created, [], ['name' => $name], $members->reject(fn (User $member) => $member->is($creator))->pluck('id')->values()->all());

        return $group;
    }

    /**
     * Add people (or bring back former members). History before their
     * arrival stays hidden from them, like WhatsApp.
     *
     * @param  Collection<int, User>  $users
     */
    public function addMembers(Conversation $group, User $actor, Collection $users, GroupEvent $event = GroupEvent::Added): void
    {
        if ($users->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($group, $users): void {
            $now = now();
            $existing = $group->participants()->whereKey($users->pluck('id')->all())->pluck('users.id')->all();
            $attributes = [
                'role' => GroupRole::Member->value,
                'joined_at' => $now,
                'history_from' => $now,
                'left_at' => null,
                'deleted_at' => null,
                'last_read_at' => null,
                'last_delivered_at' => null,
            ];

            foreach ($users as $user) {
                in_array($user->id, $existing, true)
                    ? $group->participants()->updateExistingPivot($user->id, $attributes)
                    : $group->participants()->attach($user->id, $attributes);
            }

            $group->joinRequests()->whereIn('user_id', $users->pluck('id')->all())->delete();
        });

        $group->unsetRelation('participants');

        $this->notice(
            $group,
            $event === GroupEvent::JoinedViaLink ? $users->first() : $actor,
            $event,
            $event === GroupEvent::JoinedViaLink ? [] : $users->all(),
            notifyIds: $event === GroupEvent::JoinedViaLink ? [] : $users->pluck('id')->all(),
        );
    }

    /**
     * An admin removes someone. They see the notice, then the group stops
     * for them (they keep the history up to now).
     */
    public function removeMember(Conversation $group, User $actor, User $member): void
    {
        $this->notice($group, $actor, GroupEvent::Removed, [$member], notifyIds: [$member->id]);
        $this->markLeft($group, $member);
        $this->ensureAnAdmin($group, $actor);
    }

    /**
     * The user leaves. Like WhatsApp, when the last admin leaves, the oldest
     * member becomes admin.
     */
    public function leave(Conversation $group, User $member): void
    {
        // Like WhatsApp, leaving is discreet: only the admins are told
        $this->notice($group, $member, GroupEvent::Left, audience: Message::AUDIENCE_ADMINS);
        $this->markLeft($group, $member);
        $this->ensureAnAdmin($group, $member);
    }

    public function setRole(Conversation $group, User $actor, User $member, GroupRole $role): void
    {
        $group->participants()->updateExistingPivot($member->id, ['role' => $role->value]);
        $group->unsetRelation('participants');

        $this->notice($group, $actor, $role === GroupRole::Admin ? GroupEvent::Promoted : GroupEvent::Demoted, [$member]);
    }

    /**
     * Leave a grey notice in the group. Only $notifyIds get a push, e.g.
     * those who were added or removed.
     *
     * @param  list<User>  $targets
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $notifyIds
     */
    public function notice(Conversation $group, User $actor, GroupEvent $event, array $targets = [], array $extra = [], array $notifyIds = [], ?string $audience = null): Message
    {
        $message = $this->sendMessage->handle($group, $actor, [
            'message_type' => MessageType::System->value,
            'audience' => $audience,
            'payload' => [
                'event' => $event->value,
                'actor' => ['id' => $actor->id, 'username' => $actor->username],
                'targets' => array_map(fn (User $target) => ['id' => $target->id, 'username' => $target->username], $targets),
                ...$extra,
            ],
        ], $notifyIds);

        $this->changed($group, array_map(fn (User $target) => $target->id, $targets));

        return $message;
    }

    /**
     * Tell the phones showing the group (and the people concerned, even
     * when they just left) to load it again.
     *
     * @param  list<string>  $alsoNotify
     */
    public function changed(Conversation $group, array $alsoNotify = []): void
    {
        Realtime::send(new GroupUpdated(
            $group->id,
            array_values(array_unique([...$group->members()->pluck('users.id')->all(), ...$alsoNotify])),
        ));
    }

    private function markLeft(Conversation $group, User $member): void
    {
        $group->participants()->updateExistingPivot($member->id, [
            'left_at' => now(),
            'role' => GroupRole::Member->value,
        ]);
        $group->joinRequests()->where('user_id', $member->id)->delete();
        $group->unsetRelation('participants');
    }

    /**
     * A group always keeps an admin while it has members.
     */
    private function ensureAnAdmin(Conversation $group, User $actor): void
    {
        $members = $group->members()->get();

        if ($members->isEmpty() || $members->contains(fn (User $member) => $member->pivot->role === GroupRole::Admin->value)) {
            return;
        }

        $oldest = $members->sortBy(fn (User $member) => (string) $member->pivot->joined_at)->first();
        $group->participants()->updateExistingPivot($oldest->id, ['role' => GroupRole::Admin->value]);
        $group->unsetRelation('participants');

        $this->notice($group, $oldest, GroupEvent::Promoted, [$oldest], notifyIds: [$oldest->id]);
    }
}
