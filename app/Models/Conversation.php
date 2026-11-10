<?php

namespace App\Models;

use App\Enums\ConversationType;
use App\Enums\GroupRole;
use App\Support\AvatarThumbnail;
use App\Support\PublicFile;
use Closure;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A one-to-one conversation (`direct_key` set) or a WhatsApp-like group
 * (`type` group: name, photo, description, admins and permissions).
 */
#[Fillable([
    'direct_key',
    'type',
    'name',
    'description',
    'avatar_path',
    'created_by',
    'members_can_edit_info',
    'members_can_send',
    'members_can_add',
    'approval_required',
    'disappearing_seconds',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUuids;

    /**
     * Like WhatsApp, a group holds up to 1024 members.
     */
    public const MAX_GROUP_MEMBERS = 1024;

    /**
     * The columns of conversation_participants.
     */
    public const PIVOT_COLUMNS = [
        'joined_at', 'archived_at', 'locked_at', 'muted_at', 'muted_until', 'deleted_at',
        'role', 'left_at', 'history_from', 'last_read_at', 'last_delivered_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'direct',
        'members_can_edit_info' => true,
        'members_can_send' => true,
        'members_can_add' => true,
        'approval_required' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'members_can_edit_info' => 'boolean',
            'members_can_send' => 'boolean',
            'members_can_add' => 'boolean',
            'approval_required' => 'boolean',
            'disappearing_seconds' => 'integer',
        ];
    }

    /**
     * Unique key identifying the one-to-one conversation between two users.
     */
    public static function directKeyFor(User $first, User $second): string
    {
        return static::directKeyForIds($first->id, $second->id);
    }

    public static function directKeyForIds(string $firstId, string $secondId): string
    {
        $ids = [$firstId, $secondId];
        sort($ids);

        return implode(':', $ids);
    }

    /**
     * Eager load constraint giving each participant the block state with
     * the viewer: `is_blocked_by_viewer` and `has_blocked_viewer`.
     *
     * @return array<string, Closure>
     */
    public static function participantsWithBlockStateFor(User $viewer): array
    {
        return ['participants' => fn (BelongsToMany $participants) => $participants->withExists([
            'blockers as is_blocked_by_viewer' => fn (Builder $blockers) => $blockers->whereKey($viewer->id),
            'blockedUsers as has_blocked_viewer' => fn (Builder $blocked) => $blocked->whereKey($viewer->id),
        ])];
    }

    public function isGroup(): bool
    {
        return $this->type === ConversationType::Group;
    }

    /**
     * The other participant of a one-to-one conversation.
     */
    public function otherParticipant(User $viewer): ?User
    {
        if ($this->isGroup()) {
            return null;
        }

        return $this->participants->first(fn (User $participant) => ! $participant->is($viewer));
    }

    /**
     * Whether the participant behind this pivot row muted the conversation:
     * for good when `muted_until` is null, else until that date.
     */
    public static function pivotIsMuted(object $pivot): bool
    {
        return $pivot->muted_at !== null
            && ($pivot->muted_until === null || Carbon::parse($pivot->muted_until)->isFuture());
    }

    /**
     * Set each conversation's `latestMessage` to the last message the viewer
     * can see (see Message::visibleTo()), in two queries for the whole list.
     *
     * @param  EloquentCollection<int, Conversation>  $conversations
     */
    public static function loadLatestMessageVisibleTo(EloquentCollection $conversations, User $viewer): void
    {
        $latestIds = static::query()
            ->whereKey($conversations->modelKeys())
            ->select('id')
            ->addSelect(['latest_message_id' => Message::query()
                ->select('messages.id')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->visibleTo($viewer)
                // Ids are random UUIDs: the latest message is picked by date
                ->orderByDesc('messages.created_at')
                ->orderByDesc('messages.id')
                ->limit(1)])
            ->pluck('latest_message_id', 'id');

        // With their sender: a group shows "Alice: …" in the list
        $messages = Message::query()->with('sender')->findMany($latestIds->filter()->values())->keyBy('id');

        $conversations->each(fn (Conversation $conversation) => $conversation->setRelation(
            'latestMessage',
            $messages->get($latestIds->get($conversation->id)),
        ));
    }

    /**
     * Whether the user took part, former group members included: they keep
     * reading the history up to their departure.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->pivotOf($user) !== null;
    }

    /**
     * The pivot row of the user, former members included.
     */
    public function pivotOf(User $user): ?object
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->first(fn (User $participant) => $participant->is($user))?->pivot;
        }

        return $this->participants()->whereKey($user->id)->first()?->pivot;
    }

    /**
     * Whether the user is a current member: every participant of a
     * one-to-one conversation, the members of a group who did not leave.
     */
    public function hasMember(User $user): bool
    {
        $pivot = $this->pivotOf($user);

        return $pivot !== null && $pivot->left_at === null;
    }

    public function isAdmin(User $user): bool
    {
        $pivot = $this->pivotOf($user);

        return $this->isGroup() && $pivot !== null && $pivot->left_at === null && $pivot->role === GroupRole::Admin->value;
    }

    /**
     * Name, photo and description: admins always, members when allowed.
     */
    public function canEditInfo(User $user): bool
    {
        return $this->isAdmin($user) || ($this->isGroup() && $this->members_can_edit_info && $this->hasMember($user));
    }

    public function canAddMembers(User $user): bool
    {
        return $this->isAdmin($user) || ($this->isGroup() && $this->members_can_add && $this->hasMember($user));
    }

    /**
     * Code of the invite link, created the first time an admin asks for it.
     */
    public function ensureInviteCode(): string
    {
        if ($this->invite_code === null) {
            $this->resetInviteCode();
        }

        return $this->invite_code;
    }

    /**
     * A new link: the previous one stops working.
     */
    public function resetInviteCode(): void
    {
        $this->forceFill(['invite_code' => Str::random(22)])->save();
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => PublicFile::url($this->avatar_path));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarThumbUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => AvatarThumbnail::url($this->avatar_path));
    }

    /**
     * Everyone who took part, former group members included.
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withPivot(self::PIVOT_COLUMNS);
    }

    /**
     * The current members: those who get the messages.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->participants()->wherePivotNull('left_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Requests to join through the invite link, when admins approve them.
     *
     * @return HasMany<GroupJoinRequest, $this>
     */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        // Ids are random UUIDs: the latest message is picked by date
        return $this->hasOne(Message::class)->latestOfMany(['created_at', 'id']);
    }
}
