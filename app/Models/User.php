<?php

namespace App\Models;

use App\Support\AvatarThumbnail;
use App\Support\PublicFile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'email',
    'password',
    'username',
    'first_name',
    'last_name',
    'phone_number',
    'avatar_path',
    'status',
    'is_online',
    'last_seen_at',
    'biometrics_enabled',
    'first_login_completed',
    'push_token',
    'fcm_token',
    'chat_lock_code',
])]
#[Hidden(['password', 'remember_token', 'push_token', 'fcm_token', 'chat_lock_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Hey, I use LoveGames!',
        'is_online' => false,
        'biometrics_enabled' => false,
        'first_login_completed' => false,
        'is_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'chat_lock_code' => 'hashed',
            'is_online' => 'boolean',
            'biometrics_enabled' => 'boolean',
            'first_login_completed' => 'boolean',
            'is_admin' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * A user only has a public profile once onboarding gave them a username.
     */
    public function hasProfile(): bool
    {
        return $this->username !== null;
    }

    /**
     * A moderator suspended the account: it can no longer be used.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Whether either user blocked the other. Blocked users cannot write to
     * each other, nor find each other.
     */
    public function hasBlockWith(User $other): bool
    {
        return DB::table('user_blocks')
            ->where(fn (QueryBuilder $query) => $query->where('blocker_id', $this->id)->where('blocked_id', $other->id))
            ->orWhere(fn (QueryBuilder $query) => $query->where('blocker_id', $other->id)->where('blocked_id', $this->id))
            ->exists();
    }

    public function hasBlocked(User $other): bool
    {
        return $this->blockedUsers()->whereKey($other->id)->exists();
    }

    /**
     * Whether the two users may talk and play together, like on WhatsApp:
     * one has the other's number in their phone, an invitation was
     * accepted, or they already have a conversation. Blocks are checked
     * separately (a blocked contact keeps the read-only history).
     */
    public function canReach(User $other): bool
    {
        return ! $this->is($other) && in_array($other->id, $this->reachableIdsAmong([$other->id]), true);
    }

    /**
     * The users among $ids this user can reach (see canReach()), in a few
     * queries for a whole list.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function reachableIdsAmong(array $ids): array
    {
        $ids = array_values(array_diff(array_unique($ids), [$this->id]));

        if ($ids === []) {
            return [];
        }

        $inMyPhone = DB::table('user_contacts')->where('owner_id', $this->id)->whereIn('contact_id', $ids)->pluck('contact_id');
        $haveMyNumber = DB::table('user_contacts')->where('contact_id', $this->id)->whereIn('owner_id', $ids)->pluck('owner_id');

        $invitedAccepted = ContactRequest::query()->accepted()->where('sender_id', $this->id)->whereIn('recipient_id', $ids)->pluck('recipient_id');
        $acceptedMine = ContactRequest::query()->accepted()->where('recipient_id', $this->id)->whereIn('sender_id', $ids)->pluck('sender_id');

        $keys = collect($ids)->mapWithKeys(fn (string $id) => [Conversation::directKeyForIds($this->id, $id) => $id]);
        $talkedTo = Conversation::query()->whereIn('direct_key', $keys->keys())->pluck('direct_key')->map(fn (string $key) => $keys[$key]);

        // Like WhatsApp, the members of a group can write to each other
        $inMyGroups = DB::table('conversation_participants as mine')
            ->join('conversations', 'conversations.id', '=', 'mine.conversation_id')
            ->join('conversation_participants as theirs', 'theirs.conversation_id', '=', 'mine.conversation_id')
            ->where('conversations.type', 'group')
            ->where('mine.user_id', $this->id)
            ->whereNull('mine.left_at')
            ->whereNull('theirs.left_at')
            ->whereIn('theirs.user_id', $ids)
            ->pluck('theirs.user_id');

        return $inMyPhone->merge($haveMyNumber)->merge($invitedAccepted)->merge($acceptedMine)->merge($talkedTo)->merge($inMyGroups)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * LoveGames users found in this user's phone contacts (only the matched
     * accounts are stored, never the numbers).
     *
     * @return BelongsToMany<User, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_contacts', 'owner_id', 'contact_id');
    }

    /**
     * Leave out the users the viewer blocked or was blocked by.
     */
    #[Scope]
    protected function withoutBlocksInvolving(Builder $query, User $viewer): void
    {
        $query->whereNotExists(fn (QueryBuilder $blocks) => $blocks
            ->selectRaw('1')
            ->from('user_blocks')
            ->where(fn (QueryBuilder $pair) => $pair
                ->where(fn (QueryBuilder $byViewer) => $byViewer->where('blocker_id', $viewer->id)->whereColumn('blocked_id', 'users.id'))
                ->orWhere(fn (QueryBuilder $ofViewer) => $ofViewer->whereColumn('blocker_id', 'users.id')->where('blocked_id', $viewer->id))));
    }

    /**
     * Users this user blocked.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id')
            ->withPivot('created_at');
    }

    /**
     * Reports about this account or its content.
     *
     * @return HasMany<Report, $this>
     */
    public function reportsReceived(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    /**
     * Users who blocked this user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'blocker_id');
    }

    /**
     * Every profile photo the user has set, the current one included.
     *
     * @return HasMany<ProfilePhoto, $this>
     */
    public function profilePhotos(): HasMany
    {
        return $this->hasMany(ProfilePhoto::class);
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
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(Conversation::PIVOT_COLUMNS);
    }

    /**
     * @return BelongsToMany<LoveGame, $this>
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(LoveGame::class, 'game_participants', 'user_id', 'game_id')
            ->withPivot('role', 'joined_at');
    }

    /**
     * Expo push token used by the ExpoPushChannel.
     */
    public function routeNotificationForExpo(): ?string
    {
        return $this->push_token;
    }
}
