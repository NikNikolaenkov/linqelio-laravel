<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Read;

/**
 * What a launch did: the campaign, now scheduled or running, and the recipient
 * snapshot it took.
 *
 * `explicitIncluded` below `explicitRequested` means some of the contacts you
 * named were left out — not in the cabinet, merged away, or outside the key's
 * channel scope. The snapshot is final; nobody is added to it later.
 */
final readonly class CampaignLaunch
{
    public function __construct(
        public Campaign $campaign,
        public int $recipients,
        public int $explicitRequested = 0,
        public int $explicitIncluded = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            campaign: Campaign::fromArray(Read::map($data, 'campaign')),
            recipients: Read::int($data, 'recipients'),
            explicitRequested: Read::int($data, 'explicitRequested'),
            explicitIncluded: Read::int($data, 'explicitIncluded'),
        );
    }
}
