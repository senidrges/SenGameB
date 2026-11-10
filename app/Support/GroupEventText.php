<?php

namespace App\Support;

use App\Enums\GroupEvent;
use App\Models\User;

/**
 * The sentence of a group notice, like WhatsApp: "Alice a ajouté Bob". Seen
 * by $viewer, their own name becomes "Vous". Used in push notifications;
 * the app writes the same sentences itself.
 */
class GroupEventText
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function describe(array $payload, ?User $viewer = null): string
    {
        $actor = self::name($payload['actor'] ?? null, $viewer);
        $targets = self::names($payload['targets'] ?? [], $viewer);
        $isYou = $actor === 'Vous';

        return match (GroupEvent::tryFrom((string) ($payload['event'] ?? ''))) {
            GroupEvent::Created => "{$actor} ".($isYou ? 'avez' : 'a').' créé le groupe « '.($payload['name'] ?? '').' »',
            GroupEvent::Added => "{$actor} ".($isYou ? 'avez' : 'a')." ajouté {$targets}",
            GroupEvent::Removed => "{$actor} ".($isYou ? 'avez' : 'a')." retiré {$targets}",
            GroupEvent::Left => $isYou ? 'Vous êtes parti(e)' : "{$actor} est parti(e)",
            GroupEvent::JoinedViaLink => "{$actor} ".($isYou ? 'avez' : 'a')." rejoint le groupe via un lien d'invitation",
            GroupEvent::Renamed => "{$actor} ".($isYou ? 'avez' : 'a').' modifié le nom du groupe en « '.($payload['name'] ?? '').' »',
            GroupEvent::DescriptionChanged => "{$actor} ".($isYou ? 'avez' : 'a').' modifié la description du groupe',
            GroupEvent::AvatarChanged => "{$actor} ".($isYou ? 'avez' : 'a')." modifié l'icône de ce groupe",
            GroupEvent::AvatarRemoved => "{$actor} ".($isYou ? 'avez' : 'a')." supprimé l'icône de ce groupe",
            GroupEvent::Promoted => self::isAbout($payload, $viewer) ? 'Vous êtes désormais admin' : "{$targets} est désormais admin",
            GroupEvent::Demoted => self::isAbout($payload, $viewer) ? "Vous n'êtes plus admin" : "{$targets} n'est plus admin",
            GroupEvent::SettingsChanged => "{$actor} ".($isYou ? 'avez' : 'a').' '.self::setting($payload),
            GroupEvent::LinkReset => "{$actor} ".($isYou ? 'avez' : 'a')." réinitialisé le lien d'invitation",
            GroupEvent::DisappearingChanged => ($payload['value'] ?? null) === null
                ? "{$actor} ".($isYou ? 'avez' : 'a').' désactivé les messages éphémères'
                : "{$actor} ".($isYou ? 'avez' : 'a').' activé les messages éphémères. Les nouveaux messages disparaîtront '.self::duration((int) $payload['value']).' après leur envoi',
            null => '',
        };
    }

    public static function duration(int $seconds): string
    {
        return match (true) {
            $seconds >= 7776000 => '90 jours',
            $seconds >= 604800 => '7 jours',
            default => '24 heures',
        };
    }

    /**
     * What a change of permission says, like WhatsApp.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function setting(array $payload): string
    {
        $who = ($payload['value'] ?? false) ? 'tous les membres' : 'uniquement les admins';

        return match ($payload['setting'] ?? null) {
            'members_can_send' => "modifié les paramètres de ce groupe pour autoriser {$who} à envoyer des messages",
            'members_can_edit_info' => "modifié les paramètres de ce groupe pour autoriser {$who} à modifier les infos du groupe",
            'members_can_add' => "modifié les paramètres de ce groupe pour autoriser {$who} à ajouter des membres",
            'approval_required' => ($payload['value'] ?? false)
                ? "activé l'approbation des admins pour rejoindre ce groupe"
                : "désactivé l'approbation des admins pour rejoindre ce groupe",
            default => 'modifié les paramètres du groupe',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isAbout(array $payload, ?User $viewer): bool
    {
        return $viewer !== null && collect($payload['targets'] ?? [])->contains('id', $viewer->id);
    }

    /**
     * @param  array{id?: string, username?: string}|null  $person
     */
    private static function name(?array $person, ?User $viewer): string
    {
        if ($viewer !== null && ($person['id'] ?? null) === $viewer->id) {
            return 'Vous';
        }

        return (string) ($person['username'] ?? 'Quelqu’un');
    }

    /**
     * "Bob", "Bob et Carol", "Bob, Carol et Dan".
     *
     * @param  list<array{id?: string, username?: string}>  $people
     */
    private static function names(array $people, ?User $viewer): string
    {
        $names = array_map(fn (array $person) => self::name($person, $viewer) === 'Vous' ? 'vous' : self::name($person, $viewer), $people);

        if (count($names) <= 1) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names)." et {$last}";
    }
}
