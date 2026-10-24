<?php

namespace App\Http\Resources;

use App\Models\LoveGame;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A love game, shaped like `LoveGameWithDetails` when its relations are loaded.
 *
 * @mixin LoveGame
 */
class LoveGameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'organizer_id' => $this->organizer_id,
            'title' => $this->title,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'organizer' => ProfileResource::make($this->whenLoaded('organizer')),
            'participants' => ProfileResource::collection($this->whenLoaded('participants')),
            'items' => GameItemResource::collection($this->whenLoaded('items')),
            'choices' => GameChoiceResource::collection($this->whenLoaded('choices')),
            'messages' => GameMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
