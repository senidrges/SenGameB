<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case HateSpeech = 'hate_speech';
    case Violence = 'violence';
    case SexualContent = 'sexual_content';
    case FakeAccount = 'fake_account';
    case Scam = 'scam';
    case SelfHarm = 'self_harm';
    case Underage = 'underage';
    case Other = 'other';

    /**
     * Label shown to users and moderators.
     */
    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::Harassment => 'Harcèlement ou intimidation',
            self::HateSpeech => 'Discours haineux',
            self::Violence => 'Violence ou menaces',
            self::SexualContent => 'Contenu sexuel ou inapproprié',
            self::FakeAccount => 'Faux compte ou usurpation d\'identité',
            self::Scam => 'Arnaque ou fraude',
            self::SelfHarm => 'Suicide ou automutilation',
            self::Underage => 'Utilisateur mineur',
            self::Other => 'Autre',
        };
    }
}
