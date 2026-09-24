<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where an AI run is.
 */
enum AiRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refused = 'refused';
    case Skipped = 'skipped';
}
