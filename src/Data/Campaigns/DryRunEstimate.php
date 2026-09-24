<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * How long a campaign would take, and what would hold it back.
 *
 * An ESTIMATE under today's limits — daily and hourly windows, the campaign's
 * pace, warmup, the send window, business hours, the monthly cap. `complete`
 * false means the estimate ran out of horizon before every recipient was placed.
 */
final readonly class DryRunEstimate
{
    /**
     * @param  string  $limitingFactor  the factor that dominates, e.g. `daily_limit`
     * @param  array<string, int>  $factors  factor => seconds it contributes
     */
    public function __construct(
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $finishesAt = null,
        public int $durationSeconds = 0,
        public bool $complete = false,
        public string $limitingFactor = '',
        public array $factors = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $factors = [];
        foreach (Read::objects($data, 'factors') as $factor) {
            $factors[Read::string($factor, 'factor')] = Read::int($factor, 'seconds');
        }

        return new self(
            startsAt: Read::date($data, 'startsAt'),
            finishesAt: Read::date($data, 'finishesAt'),
            durationSeconds: Read::int($data, 'durationSeconds'),
            complete: Read::bool($data, 'complete'),
            limitingFactor: Read::string($data, 'limitingFactor'),
            factors: $factors,
        );
    }
}
