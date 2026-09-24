<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Groups;

use Linqelio\Laravel\Data\Enums\GroupMemberFailureReason;

/**
 * One member of a group change. `reason` is set only for a member it failed for.
 */
final readonly class GroupMemberOutcome
{
    public function __construct(
        public ?string $contactId = null,
        public ?string $providerId = null,
        public ?GroupMemberFailureReason $reason = null,
    ) {}
}
