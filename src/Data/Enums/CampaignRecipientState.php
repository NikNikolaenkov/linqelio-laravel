<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * Where one recipient of a campaign is.
 */
enum CampaignRecipientState: string
{
    case Queued = 'queued';
    case Deferred = 'deferred';
    case Sending = 'sending';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
