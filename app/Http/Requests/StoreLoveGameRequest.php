<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoveGameRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'player_ids' => ['required', 'array', 'min:1', 'max:20'],
            'player_ids.*' => [
                'uuid',
                'distinct',
                Rule::notIn([$this->user()->id]),
                Rule::exists('users', 'id')->whereNotNull('username'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $player = User::find($value);

                    if ($player !== null && $this->user()->hasBlockWith($player)) {
                        $fail('Vous ne pouvez pas inviter cet utilisateur.');
                    }
                },
            ],
            ...GameItemRules::rules(),
            'conversation_id' => [
                'nullable',
                'uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $conversation = Conversation::find($value);

                    if ($conversation === null || ! $conversation->hasParticipant($this->user())) {
                        $fail('Conversation introuvable.');
                    }
                },
            ],
        ];
    }
}
