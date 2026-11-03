<?php

namespace App\Notifications\Messages;

/**
 * A push notification sent through the Expo push service.
 */
class ExpoMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
        public string $channelId = 'default',
        public ?string $categoryId = null,
        // "high" wakes the phone right away (calls)
        public ?string $priority = null,
        // Seconds after which an undelivered push is dropped
        public ?int $ttl = null,
    ) {}

    /**
     * Payload expected by the Expo push API.
     *
     * @return array<string, mixed>
     */
    public function toPayload(string $token): array
    {
        return array_filter([
            'to' => $token,
            'title' => $this->title,
            'body' => $this->body,
            'data' => (object) $this->data,
            'sound' => 'default',
            'badge' => 1,
            'channelId' => $this->channelId,
            'categoryId' => $this->categoryId,
            'priority' => $this->priority,
            'ttl' => $this->ttl,
        ], fn (mixed $value): bool => $value !== null);
    }
}
