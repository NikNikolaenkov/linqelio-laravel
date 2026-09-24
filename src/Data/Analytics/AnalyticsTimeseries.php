<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\AnalyticsGranularity;
use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Enums\AnalyticsUnit;
use Linqelio\Laravel\Data\Read;

/**
 * One metric over time, bucketed by hour, day or week in the cabinet's zone.
 * A bucket with nothing to measure has a null `value`, not zero.
 */
final readonly class AnalyticsTimeseries
{
    /**
     * @param  array<int, array{start: ?DateTimeImmutable, label: string, value: ?float}>  $points
     */
    public function __construct(
        public ?AnalyticsMetric $metric,
        public ?AnalyticsUnit $unit,
        public ?AnalyticsGranularity $granularity,
        public AnalyticsPeriod $period,
        public bool $scoped = false,
        public array $points = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $points = [];
        foreach (Read::objects($data, 'points') as $point) {
            $points[] = [
                'start' => Read::date($point, 'start'),
                'label' => Read::string($point, 'label'),
                'value' => Read::floatOrNull($point, 'value'),
            ];
        }

        return new self(
            metric: AnalyticsMetric::tryFrom(Read::string($data, 'metric')),
            unit: AnalyticsUnit::tryFrom(Read::string($data, 'unit')),
            granularity: AnalyticsGranularity::tryFrom(Read::string($data, 'granularity')),
            period: AnalyticsPeriod::fromArray(Read::map($data, 'period')),
            scoped: Read::bool($data, 'scoped'),
            points: $points,
        );
    }
}
