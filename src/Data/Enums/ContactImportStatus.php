<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where a contact import is: uploaded and waiting for a mapping, then running
 * in the background until it finishes one way or another.
 */
enum ContactImportStatus: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            default => false,
        };
    }
}
