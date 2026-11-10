<?php

namespace App\Enums;

/**
 * What a system message of a group says, like the grey notices of
 * WhatsApp groups ("Alice added Bob", "Bob left"…). The app writes the
 * sentence from the payload: "You" for the viewer, the names otherwise.
 */
enum GroupEvent: string
{
    case Created = 'created';
    case Added = 'added';
    case Removed = 'removed';
    case Left = 'left';
    case JoinedViaLink = 'joined_via_link';
    case Renamed = 'renamed';
    case DescriptionChanged = 'description_changed';
    case AvatarChanged = 'avatar_changed';
    case AvatarRemoved = 'avatar_removed';
    case Promoted = 'promoted';
    case Demoted = 'demoted';
    case SettingsChanged = 'settings_changed';
    case LinkReset = 'link_reset';
    // Disappearing messages turned on (value: seconds) or off (null)
    case DisappearingChanged = 'disappearing_changed';
}
