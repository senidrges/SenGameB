<?php

namespace App\Models;

use Database\Factories\GameChoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'user_id', 'choice_number', 'chosen_at'])]
class GameChoice extends Model
{
    /** @use HasFactory<GameChoiceFactory> */
    use HasFactory, HasUuids;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'choice_number' => 'integer',
            'chosen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LoveGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(LoveGame::class, 'game_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
