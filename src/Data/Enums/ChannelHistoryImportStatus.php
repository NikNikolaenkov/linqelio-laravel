<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where a Telegram account's history import is.
 *
 * `none`: the channel never ran one. `pending`: waiting for the channel's
 * session. `running`: reading chats. `paused`: Telegram asked to slow down, it
 * resumes by itself. `done` and `failed` are final.
 */
enum ChannelHistoryImportStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Done = 'done';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
