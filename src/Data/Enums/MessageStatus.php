<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * How far an outbound message has got.
 *
 * `queued` is what a send returns: the API accepts the command (202) and the
 * provider is reached afterwards, so the interesting statuses arrive later —
 * over a webhook or on a re-read, never in the send's own response.
 */
enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    /** Nothing further will happen to a message in this state. */
    public function isTerminal(): bool
    {
        return $this === self::Read || $this === self::Failed;
    }
}
