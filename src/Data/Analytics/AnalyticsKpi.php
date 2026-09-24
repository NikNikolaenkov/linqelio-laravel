<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Enums\AnalyticsUnit;
use Linqelio\Laravel\Data\Read;

/**
 * One key number of a period, against the period before it.
 *
 * `value` is null when there is nothing to measure (a rate with no messages), not
 * zero — "0 % delivered" and "nothing sent" are different answers.
 */
final readonly class AnalyticsKpi
{
    public function __construct(
        public ?AnalyticsMetric $metric,
        public ?AnalyticsUnit $unit,
        public ?float $value = null,
        public ?float $previous = null,
        public ?float $delta = null,
        public ?float $deltaPct = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            metric: AnalyticsMetric::tryFrom(Read::string($data, 'metric')),
            unit: AnalyticsUnit::tryFrom(Read::string($data, 'unit')),
            value: Read::floatOrNull($data, 'value'),
            previous: Read::floatOrNull($data, 'previous'),
            delta: Read::floatOrNull($data, 'delta'),
            deltaPct: Read::floatOrNull($data, 'deltaPct'),
        );
    }
}
