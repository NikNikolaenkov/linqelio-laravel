<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use Linqelio\Laravel\Data\Enums\AnalyticsBreakdownBy;
use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Read;

/**
 * A period's numbers per channel, agent, origin or policy reason.
 */
final readonly class AnalyticsBreakdown
{
    /**
     * @param  array<int, AnalyticsMetric>  $metrics  the columns every row carries
     * @param  array<int, array{key: string, label: string, kind: ?string, values: array<string, float>}>  $rows  `values` keyed by metric value
     */
    public function __construct(
        public ?AnalyticsBreakdownBy $by,
        public AnalyticsPeriod $period,
        public bool $scoped = false,
        public array $metrics = [],
        public array $rows = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $metrics = [];
        foreach (Read::strings($data, 'metrics') as $metric) {
            $parsed = AnalyticsMetric::tryFrom($metric);
            if ($parsed !== null) {
                $metrics[] = $parsed;
            }
        }

        $rows = [];
        foreach (Read::objects($data, 'rows') as $row) {
            $rows[] = [
                'key' => Read::string($row, 'key'),
                'label' => Read::string($row, 'label'),
                'kind' => Read::stringOrNull($row, 'kind'),
                'values' => Read::floatMap($row, 'values'),
            ];
        }

        return new self(
            by: AnalyticsBreakdownBy::tryFrom(Read::string($data, 'by')),
            period: AnalyticsPeriod::fromArray(Read::map($data, 'period')),
            scoped: Read::bool($data, 'scoped'),
            metrics: $metrics,
            rows: $rows,
        );
    }
}
