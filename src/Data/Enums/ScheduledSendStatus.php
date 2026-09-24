<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where one scheduled message is.
 */
enum ScheduledSendStatus: string
{
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** Still waiting for its moment — the only state that can be moved or cancelled. */
    public function isPending(): bool
    {
        return $this === self::Scheduled;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Sent, self::Failed, self::Cancelled => true,
            default => false,
        };
    }
}
