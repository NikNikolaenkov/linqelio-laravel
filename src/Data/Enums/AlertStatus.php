<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The lifecycle of an alert.
 */
enum AlertStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
