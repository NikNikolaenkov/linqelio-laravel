<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Read;

/**
 * How far a campaign's recipients have got. Counts, one per recipient state.
 */
final readonly class CampaignProgress
{
    public function __construct(
        public int $total = 0,
        public int $queued = 0,
        public int $deferred = 0,
        public int $sending = 0,
        public int $sent = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public int $cancelled = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: Read::int($data, 'total'),
            queued: Read::int($data, 'queued'),
            deferred: Read::int($data, 'deferred'),
            sending: Read::int($data, 'sending'),
            sent: Read::int($data, 'sent'),
            skipped: Read::int($data, 'skipped'),
            failed: Read::int($data, 'failed'),
            cancelled: Read::int($data, 'cancelled'),
        );
    }

    /** Recipients still to be tried: queued, deferred or in flight. */
    public function pending(): int
    {
        return $this->queued + $this->deferred + $this->sending;
    }
}
