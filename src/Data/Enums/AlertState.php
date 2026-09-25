<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The `state` filter of an alert list: a coarser cut than {@see AlertStatus}.
 */
enum AlertState: string
{
    /** Still needing attention: open or acknowledged. */
    case Active = 'active';

    /** Every status (the platform's default). */
    case All = 'all';
}
