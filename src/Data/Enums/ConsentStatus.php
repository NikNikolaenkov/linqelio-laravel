<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Whether a contact may be messaged on one channel (ADR-0084).
 */
enum ConsentStatus: string
{
    case Granted = 'granted';
    case Revoked = 'revoked';
    case Missing = 'missing';
}
