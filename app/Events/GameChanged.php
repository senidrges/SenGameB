<?php

namespace App\Events;

use App\Http\Resources\LoveGameResource;
use App\Models\LoveGame;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A game was created, updated or deleted.
 *
 * Sent to the game channel and to each participant's personal channel so
 * the games list stays in sync.
 */
class GameChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public const CREATED = 'game.created';

    public const UPDATED = 'game.updated';

    public const DELETED = 'game.deleted';

    /**
     * Base attributes are captured right away so a deleted game can still
     * be described.
     *
     * @var array<string, mixed>
     */
    public array $game;

    /**
     * @param  list<string>  $participantIds
     */
    public function __construct(LoveGame $game, public array $participantIds, public string $type = self::UPDATED)
    {
        $this->game = LoveGameResource::make($game->withoutRelations())->resolve();
    }

    public static function created(LoveGame $game): self
    {
        return new self($game, $game->participantIds(), self::CREATED);
    }

    public static function updated(LoveGame $game): self
    {
        return new self($game, $game->participantIds(), self::UPDATED);
    }

    /**
     * @param  list<string>  $participantIds
     */
    public static function deleted(LoveGame $game, array $participantIds): self
    {
        return new self($game, $participantIds, self::DELETED);
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("games.{$this->game['id']}"),
            ...array_map(fn (string $id) => new PrivateChannel("users.{$id}"), $this->participantIds),
        ];
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['game' => $this->game];
    }
}
