<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Why a contact could not be added to (or removed from) a group.
 */
enum GroupMemberFailureReason: string
{
    case ContactNotFound = 'contact_not_found';
    case NoAddress = 'no_address';
    case Refused = 'refused';
}
