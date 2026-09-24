<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * A campaign or a scheduled send refused the change: not found, a state that
 * does not allow it (`campaign.state_conflict`, `scheduled_send.state_conflict`),
 * an invalid draft, or a launch without a fresh dry run.
 */
class CampaignException extends LinqelioException
{
    use HasFieldErrors;
}
