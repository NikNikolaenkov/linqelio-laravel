<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Whether a contact merge still stands.
 */
enum MergeStatus: string
{
    case Active = 'active';
    case Undone = 'undone';
}
