<?php

namespace App\Models;

use Database\Factories\GameItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'number', 'content'])]
class GameItem extends Model
{
    /** @use HasFactory<GameItemFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LoveGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(LoveGame::class, 'game_id');
    }
}
