<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where a background export (contacts or an analytics report) is.
 *
 * `expired` is a completed export whose file has been swept: the record stays,
 * the bytes do not, and a download answers 410.
 */
enum ExportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    /** The file is there to download. */
    public function isReady(): bool
    {
        return $this === self::Completed;
    }

    public function isFinished(): bool
    {
        return $this !== self::Queued && $this !== self::Running;
    }
}
