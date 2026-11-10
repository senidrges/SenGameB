<?php

namespace App\Http\Controllers\Api;

use App\Actions\ManageGroup;
use App\Enums\GroupEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Support\AvatarThumbnail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * WhatsApp-like groups: creation, name, description, photo and permissions.
 */
class GroupController extends Controller
{
    /**
     * Create a group with people the creator can reach (their contacts,
     * like WhatsApp). The photo may come with it.
     */
    public function store(Request $request, ManageGroup $manageGroup): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2048'],
            'member_ids' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_GROUP_MEMBERS - 1)],
            'member_ids.*' => ['required', 'uuid', 'distinct', Rule::notIn([$user->id])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $members = self::reachableUsers($user, $validated['member_ids'], 'member_ids');

        $group = $manageGroup->create($user, trim($validated['name']), $validated['description'] ?? null, $members);

        if ($request->hasFile('avatar')) {
            $group->forceFill(['avatar_path' => $this->saveAvatar($request, $group)])->save();
        }

        return $this->show($request, $group)->response()->setStatusCode(201);
    }

    /**
     * Name and description: admins, or every member when the group allows it.
     */
    public function update(Request $request, Conversation $conversation, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        Gate::authorize('editGroupInfo', $conversation);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();

        if (array_key_exists('name', $validated) && trim($validated['name']) !== $conversation->name) {
            $conversation->update(['name' => trim($validated['name'])]);
            $manageGroup->notice($conversation, $user, GroupEvent::Renamed, extra: ['name' => $conversation->name]);
        }

        if (array_key_exists('description', $validated) && $validated['description'] !== $conversation->description) {
            $conversation->update(['description' => $validated['description']]);
            $manageGroup->notice($conversation, $user, GroupEvent::DescriptionChanged);
        }

        return $this->show($request, $conversation);
    }

    /**
     * "Paramètres du groupe": who edits the infos, who writes, who adds
     * members and whether admins approve those who join by link. Admins only.
     */
    public function settings(Request $request, Conversation $conversation, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        Gate::authorize('administer', $conversation);

        $validated = $request->validate([
            'members_can_edit_info' => ['sometimes', 'boolean'],
            'members_can_send' => ['sometimes', 'boolean'],
            'members_can_add' => ['sometimes', 'boolean'],
            'approval_required' => ['sometimes', 'boolean'],
        ]);

        foreach ($validated as $setting => $value) {
            if ($conversation->{$setting} === (bool) $value) {
                continue;
            }

            $conversation->update([$setting => (bool) $value]);
            $manageGroup->notice($conversation, $request->user(), GroupEvent::SettingsChanged, extra: [
                'setting' => $setting,
                'value' => (bool) $value,
            ]);
        }

        return $this->show($request, $conversation);
    }

    private function saveAvatar(Request $request, Conversation $conversation): string
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('avatar')->store("group-avatars/{$conversation->id}", 'public');
        AvatarThumbnail::create($path);

        return $path;
    }

    /**
     * Change the group's photo.
     */
    public function updateAvatar(Request $request, Conversation $conversation, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        Gate::authorize('editGroupInfo', $conversation);

        $conversation->update(['avatar_path' => $this->saveAvatar($request, $conversation)]);
        $manageGroup->notice($conversation, $request->user(), GroupEvent::AvatarChanged);

        return $this->show($request, $conversation);
    }

    public function destroyAvatar(Request $request, Conversation $conversation, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        Gate::authorize('editGroupInfo', $conversation);

        if ($conversation->avatar_path !== null) {
            $conversation->update(['avatar_path' => null]);
            $manageGroup->notice($conversation, $request->user(), GroupEvent::AvatarRemoved);
        }

        return $this->show($request, $conversation);
    }

    private function show(Request $request, Conversation $conversation): ConversationResource
    {
        $conversation->unsetRelation('participants');
        $conversation->load(Conversation::participantsWithBlockStateFor($request->user()));
        Conversation::loadLatestMessageVisibleTo($conversation->newCollection([$conversation]), $request->user());

        return ConversationResource::make($conversation)->withMembers();
    }

    private function ensureGroup(Conversation $conversation): void
    {
        abort_unless($conversation->isGroup(), 404);
    }

    /**
     * The users among $ids that $user may put in a group: with a profile,
     * reachable (their number, an accepted invitation or a conversation) and
     * without a block either way.
     *
     * @param  list<string>  $ids
     * @return Collection<int, User>
     */
    public static function reachableUsers(User $user, array $ids, string $field): Collection
    {
        $reachable = $user->reachableIdsAmong($ids);

        $users = User::query()
            ->whereKey($reachable)
            ->whereNotNull('username')
            ->get()
            ->reject(fn (User $other) => $user->hasBlockWith($other))
            ->values();

        if ($users->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                $field => 'Vous ne pouvez ajouter que des personnes dont vous avez le numéro ou qui ont accepté votre invitation.',
            ]);
        }

        return $users;
    }
}
