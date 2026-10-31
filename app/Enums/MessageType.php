<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case File = 'file';
    case Location = 'location';
    case Contact = 'contact';
    case Poll = 'poll';
    case Event = 'event';

    /**
     * Whether the message carries an uploaded file.
     */
    public function hasAttachment(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::File], true);
    }

    /**
     * Whether people answer the message (poll options, event attendance).
     */
    public function acceptsVotes(): bool
    {
        return in_array($this, [self::Poll, self::Event], true);
    }

    /**
     * Short text used in push notifications for this kind of message.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function previewLabel(?string $content, ?array $payload = null): string
    {
        return match ($this) {
            self::Text => (string) $content,
            self::Image => '📷 Photo',
            self::Audio => '🎵 Message vocal',
            self::File => '📎 Fichier',
            self::Location => '📍 Localisation',
            self::Contact => '👤 Contact : '.($payload['name'] ?? ''),
            self::Poll => '📊 Sondage : '.($payload['question'] ?? ''),
            self::Event => '📅 Événement : '.($payload['title'] ?? ''),
        };
    }
}
