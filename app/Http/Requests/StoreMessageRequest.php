<?php

namespace App\Http\Requests;

use App\Enums\MessageType;
use App\Models\Conversation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Throws with the reason (e.g. a block) so the app can show it.
        Gate::authorize('sendMessage', $this->conversation());

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $conversation = $this->conversation();

        return [
            'message_type' => ['required', Rule::enum(MessageType::class)],
            'content' => ['required_if:message_type,text', 'nullable', 'string', 'max:5000'],
            'attachment_path' => [
                'exclude_if:message_type,text',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($conversation): void {
                    if (! str_starts_with($value, "chat-media/{$conversation->id}/") || str_contains($value, '..') || ! Storage::disk('public')->exists($value)) {
                        $fail('Le fichier joint est introuvable.');
                    }
                },
            ],
            'file_name' => ['exclude_if:message_type,text', 'nullable', 'string', 'max:255'],
            'file_size' => ['exclude_if:message_type,text', 'nullable', 'integer', 'min:0'],
            'reply_to_id' => [
                'nullable',
                'uuid',
                Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
            ],
        ];
    }

    /**
     * Attributes of the message to create.
     *
     * @return array{message_type: string, content: string|null, file_path: string|null, file_name: string|null, file_size: int|null, reply_to_id: string|null}
     */
    public function messageAttributes(): array
    {
        $validated = $this->validated();
        $isText = $validated['message_type'] === MessageType::Text->value;

        return [
            'message_type' => $validated['message_type'],
            'content' => $isText ? $validated['content'] : null,
            'file_path' => $validated['attachment_path'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ];
    }

    private function conversation(): Conversation
    {
        return $this->route('conversation');
    }
}
