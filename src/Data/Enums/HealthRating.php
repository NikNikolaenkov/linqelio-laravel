<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * A channel's health score, bucketed.
 *
 * `unknown` is not "fine": it means there was not enough traffic to judge, and a
 * channel nobody sends through can be banned just the same.
 */
enum HealthRating: string
{
    case Good = 'good';
    case Warning = 'warning';
    case Critical = 'critical';
    case Unknown = 'unknown';

    public static function parse(?string $value): self
    {
        return $value === null ? self::Unknown : (self::tryFrom($value) ?? self::Unknown);
    }
}
