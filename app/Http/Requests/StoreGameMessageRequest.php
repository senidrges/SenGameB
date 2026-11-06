<?php

namespace App\Http\Requests;

use App\Models\LoveGame;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A message of a game chat: the same kinds of messages as in a conversation.
 */
class StoreGameMessageRequest extends StoreMessageRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('view', $this->game());

        return true;
    }

    /**
     * Older versions of the app only send text, without its type.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('message_type')) {
            $this->merge(['message_type' => 'text']);
        }
    }

    protected function attachmentFolder(): string
    {
        return "game-media/{$this->game()->id}";
    }

    protected function replyToRule(): Exists
    {
        return Rule::exists('game_messages', 'id')->where('game_id', $this->game()->id);
    }

    private function game(): LoveGame
    {
        return $this->route('game');
    }
}
