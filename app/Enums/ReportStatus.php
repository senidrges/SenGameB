<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    // Reviewed, no rule was broken.
    case Dismissed = 'dismissed';
    // Reviewed, the moderator acted (content removed, account suspended...).
    case Resolved = 'resolved';
}
