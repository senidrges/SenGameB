<?php

namespace App\Http\Requests;

use App\Enums\MessageType;
use App\Models\Conversation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
                'exclude_unless:message_type,image,audio,file',
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($conversation): void {
                    if (! str_starts_with($value, "chat-media/{$conversation->id}/") || str_contains($value, '..') || ! Storage::disk('public')->exists($value)) {
                        $fail('Le fichier joint est introuvable.');
                    }
                },
            ],
            'file_name' => ['exclude_unless:message_type,image,audio,file', 'nullable', 'string', 'max:255'],
            'file_size' => ['exclude_unless:message_type,image,audio,file', 'nullable', 'integer', 'min:0'],
            ...$this->payloadRules(),
            'reply_to_id' => [
                'nullable',
                'uuid',
                Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
            ],
        ];
    }

    /**
     * Rules of the data of a shared location, contact, poll or event.
     *
     * @return array<string, mixed>
     */
    private function payloadRules(): array
    {
        return match (MessageType::tryFrom((string) $this->input('message_type'))) {
            MessageType::Location => [
                'payload' => ['required', 'array'],
                'payload.latitude' => ['required', 'numeric', 'between:-90,90'],
                'payload.longitude' => ['required', 'numeric', 'between:-180,180'],
                'payload.name' => ['nullable', 'string', 'max:255'],
                'payload.address' => ['nullable', 'string', 'max:500'],
            ],
            MessageType::Contact => [
                'payload' => ['required', 'array'],
                'payload.name' => ['required', 'string', 'max:255'],
                'payload.phone_numbers' => ['required', 'array', 'min:1', 'max:10'],
                'payload.phone_numbers.*' => ['required', 'string', 'max:30'],
            ],
            MessageType::Poll => [
                'payload' => ['required', 'array'],
                'payload.question' => ['required', 'string', 'max:255'],
                'payload.options' => ['required', 'array', 'list', 'min:2', 'max:12'],
                'payload.options.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
                'payload.allow_multiple' => ['boolean'],
            ],
            MessageType::Event => [
                'payload' => ['required', 'array'],
                'payload.title' => ['required', 'string', 'max:255'],
                'payload.description' => ['nullable', 'string', 'max:2000'],
                'payload.starts_at' => ['required', 'date'],
                'payload.ends_at' => ['nullable', 'date', 'after:payload.starts_at'],
                'payload.location' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    /**
     * Attributes of the message to create.
     *
     * @return array{message_type: string, content: string|null, file_path: string|null, file_name: string|null, file_size: int|null, payload: array<string, mixed>|null, reply_to_id: string|null}
     */
    public function messageAttributes(): array
    {
        $validated = $this->validated();
        $type = MessageType::from($validated['message_type']);

        return [
            'message_type' => $type->value,
            'content' => $type === MessageType::Text ? $validated['content'] : null,
            'file_path' => $validated['attachment_path'] ?? null,
            'file_name' => $validated['file_name'] ?? null,
            'file_size' => $validated['file_size'] ?? null,
            'payload' => $this->payloadOf($type, $validated['payload'] ?? []),
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ];
    }

    /**
     * Keep only the validated fields. Poll options get the id that votes
     * refer to.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function payloadOf(MessageType $type, array $payload): ?array
    {
        return match ($type) {
            MessageType::Location => [
                'latitude' => (float) $payload['latitude'],
                'longitude' => (float) $payload['longitude'],
                'name' => $payload['name'] ?? null,
                'address' => $payload['address'] ?? null,
            ],
            MessageType::Contact => [
                'name' => $payload['name'],
                'phone_numbers' => array_values($payload['phone_numbers']),
            ],
            MessageType::Poll => [
                'question' => $payload['question'],
                'options' => collect($payload['options'])
                    ->map(fn (string $text, int $index): array => ['id' => 'o'.($index + 1), 'text' => $text])
                    ->all(),
                'allow_multiple' => (bool) ($payload['allow_multiple'] ?? false),
            ],
            MessageType::Event => [
                'title' => $payload['title'],
                'description' => $payload['description'] ?? null,
                'starts_at' => Carbon::parse($payload['starts_at'])->toIso8601String(),
                'ends_at' => isset($payload['ends_at']) ? Carbon::parse($payload['ends_at'])->toIso8601String() : null,
                'location' => $payload['location'] ?? null,
            ],
            default => null,
        };
    }

    private function conversation(): Conversation
    {
        return $this->route('conversation');
    }
}
