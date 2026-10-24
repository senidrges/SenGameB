<?php

namespace App\Models;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use Database\Factories\LoveGameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conversation_id', 'organizer_id', 'title', 'status'])]
class LoveGame extends Model
{
    /** @use HasFactory<LoveGameFactory> */
    use HasFactory, HasUuids;

    /**
     * Relations needed to render a game with all its details.
     *
     * @var list<string>
     */
    public const DETAIL_RELATIONS = [
        'organizer',
        'participants',
        'choices',
        'items',
        'messages.sender',
        'messages.replyTo.sender',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'waiting',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
        ];
    }

    #[Scope]
    protected function withParticipant(Builder $query, User $user): void
    {
        $query->whereHas('participants', fn (Builder $participants) => $participants->whereKey($user->id));
    }

    public function isOrganizedBy(User $user): bool
    {
        return $this->organizer_id === $user->id;
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    public function hasPlayer(User $user): bool
    {
        return $this->players()->whereKey($user->id)->exists();
    }

    /**
     * Ids of everyone involved in the game, organizer included.
     *
     * @return list<string>
     */
    public function participantIds(): array
    {
        return $this->participants()->pluck('users.id')->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'game_participants', 'game_id', 'user_id')
            ->withPivot('role', 'joined_at');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->participants()->wherePivot('role', GameRole::Player->value);
    }

    /**
     * @return HasMany<GameItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GameItem::class, 'game_id')->orderBy('number');
    }

    /**
     * @return HasMany<GameChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(GameChoice::class, 'game_id');
    }

    /**
     * @return HasMany<GameMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(GameMessage::class, 'game_id')->orderBy('created_at');
    }
}
