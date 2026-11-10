<?php

namespace App\Enums;

use App\Support\GroupEventText;

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
    // Trace of an audio or video call, written by the server (see FinishCall)
    case Call = 'call';
    // Notice of a group (member added, name changed…), written by the server
    case System = 'system';

    /**
     * Whether the message carries an uploaded file.
     */
    public function hasAttachment(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::File], true);
    }

    /**
     * Whether users can send this kind of message themselves (call traces
     * are only written by the server).
     */
    public function canBeSent(): bool
    {
        return $this !== self::Call && $this !== self::System;
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
     * Like WhatsApp: 🎤 for a voice message recorded with the microphone, 🎵
     * for a music file sent as an attachment, 📄 for any other document.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function previewLabel(?string $content, ?array $payload = null, ?string $fileName = null): string
    {
        return match ($this) {
            self::Text => (string) $content,
            self::Image => '📷 Photo',
            self::Audio => '🎤 Message vocal',
            self::File => (self::isMusicFile($fileName) ? '🎵 ' : '📄 ').($fileName ?: 'Fichier'),
            self::Location => '📍 Localisation',
            self::Contact => '👤 Contact : '.($payload['name'] ?? ''),
            self::Poll => '📊 Sondage : '.($payload['question'] ?? ''),
            self::Event => '📅 Événement : '.($payload['title'] ?? ''),
            self::Call => match ($payload['status'] ?? null) {
                'missed' => '📞 Appel manqué',
                'declined' => '📞 Appel refusé',
                default => ($payload['call_type'] ?? null) === 'video' ? '📹 Appel vidéo' : '📞 Appel audio',
            },
            self::System => GroupEventText::describe($payload ?? []),
        };
    }

    /**
     * Whether an attached file is music (or another audio file).
     */
    private static function isMusicFile(?string $fileName): bool
    {
        $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));

        return in_array($extension, ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga', 'opus', 'flac', 'amr', 'wma'], true);
    }
}
