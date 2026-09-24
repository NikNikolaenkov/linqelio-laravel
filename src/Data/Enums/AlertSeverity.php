<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * How loud an alert is. Ordered: a subscription's `minSeverity` delivers that
 * level and everything above it.
 */
enum AlertSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Critical => 2,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
