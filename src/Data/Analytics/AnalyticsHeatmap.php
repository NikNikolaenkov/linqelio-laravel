<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use Linqelio\Laravel\Data\Read;

/**
 * Messages by weekday and hour of day, in the cabinet's time zone.
 */
final readonly class AnalyticsHeatmap
{
    /**
     * @param  array<int, array<int, int>>  $cells  [weekday][hour] => messages
     * @param  int  $max  the largest cell, for scaling a colour ramp
     */
    public function __construct(
        public string $direction,
        public AnalyticsPeriod $period,
        public bool $scoped = false,
        public array $cells = [],
        public int $max = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $cells = [];
        foreach (Read::objects($data, 'cells') as $row) {
            $cells[] = array_values(array_map(
                static fn (mixed $count): int => is_numeric($count) ? (int) $count : 0,
                $row,
            ));
        }

        return new self(
            direction: Read::string($data, 'direction'),
            period: AnalyticsPeriod::fromArray(Read::map($data, 'period')),
            scoped: Read::bool($data, 'scoped'),
            cells: $cells,
            max: Read::int($data, 'max'),
        );
    }
}
