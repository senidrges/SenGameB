<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case File = 'file';

    /**
     * Short text used in push notifications for this kind of message.
     */
    public function previewLabel(?string $content): string
    {
        return match ($this) {
            self::Text => (string) $content,
            self::Image => '📷 Photo',
            self::Audio => '🎵 Message vocal',
            self::File => '📎 Fichier',
        };
    }
}
