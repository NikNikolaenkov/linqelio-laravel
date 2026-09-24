<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The lifecycle of a campaign.
 *
 * draft → (dry run) → launch → scheduled | running ⇄ paused → completed, and
 * cancelled or failed from anywhere past the draft.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /** Nothing further will be sent; the campaign can be deleted. */
    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::Failed => true,
            default => false,
        };
    }

    /** Still editable: only a draft takes a new audience, message or schedule. */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }
}
