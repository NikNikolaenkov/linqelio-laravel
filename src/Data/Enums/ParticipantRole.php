<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * A participant's role in a group conversation.
 */
enum ParticipantRole: string
{
    case Member = 'member';
    case Admin = 'admin';
    case Owner = 'owner';
}
