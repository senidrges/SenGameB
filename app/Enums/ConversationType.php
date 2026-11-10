<?php

namespace App\Enums;

enum ConversationType: string
{
    // One-to-one conversation between two users
    case Direct = 'direct';
    // WhatsApp-like group with admins
    case Group = 'group';
}
