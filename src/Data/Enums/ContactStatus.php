<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The lifecycle status of a contact.
 */
enum ContactStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Archived = 'archived';
}
