<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where the aggregation of a cabinet's history — the time before live
 * aggregation started — is. Until it is `done`, a report over that past
 * undercounts it.
 */
enum AnalyticsBackfillStatus: string
{
    /** The first aggregation pass has not seen the cabinet yet. */
    case None = 'none';
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    /** History is still being filled in: past numbers may grow. */
    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Running;
    }
}
