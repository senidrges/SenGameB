<?php

namespace App\Http\Resources;

use App\Models\GameChoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameChoice
 */
class GameChoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'game_id' => $this->game_id,
            'user_id' => $this->user_id,
            'choice_number' => $this->choice_number,
            'chosen_at' => $this->chosen_at,
        ];
    }
}
