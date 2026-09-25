<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\AnalyticsBackfillStatus;
use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Read;

/**
 * The key numbers of a period, against the one before it.
 *
 * Analytics are computed in passes, not live: `lastPassAt` says how fresh the
 * numbers are, and `backfill` whether history before the install is still being
 * filled in — a report during a backfill undercounts the past.
 */
final readonly class AnalyticsOverview
{
    /**
     * @param  bool  $scoped  the key's channel scope narrowed the numbers
     * @param  array<int, AnalyticsKpi>  $kpis
     * @param  string  $backfill  none | pending | running | done | failed; see backfillStatus()
     * @param  float  $backfillProgress  0..1
     */
    public function __construct(
        public AnalyticsPeriod $period,
        public AnalyticsPeriod $previousPeriod,
        public bool $scoped = false,
        public array $kpis = [],
        public ?DateTimeImmutable $lastPassAt = null,
        public ?DateTimeImmutable $watermark = null,
        public string $backfill = 'none',
        public float $backfillProgress = 0.0,
        public ?DateTimeImmutable $historyFrom = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $freshness = Read::map($data, 'freshness');

        return new self(
            period: AnalyticsPeriod::fromArray(Read::map($data, 'period')),
            previousPeriod: AnalyticsPeriod::fromArray(Read::map($data, 'previousPeriod')),
            scoped: Read::bool($data, 'scoped'),
            kpis: array_map(AnalyticsKpi::fromArray(...), Read::objects($data, 'kpis')),
            lastPassAt: Read::date($freshness, 'lastPassAt'),
            watermark: Read::date($freshness, 'watermark'),
            backfill: Read::string($freshness, 'backfill', 'none'),
            backfillProgress: Read::float($freshness, 'backfillProgress'),
            historyFrom: Read::date($freshness, 'historyFrom'),
        );
    }

    /**
     * `backfill` as the contract's enum; null for a value newer than this
     * package. The raw string stays on {@see self::$backfill}.
     */
    public function backfillStatus(): ?AnalyticsBackfillStatus
    {
        return AnalyticsBackfillStatus::tryFrom($this->backfill);
    }

    public function kpi(AnalyticsMetric $metric): ?AnalyticsKpi
    {
        foreach ($this->kpis as $kpi) {
            if ($kpi->metric === $metric) {
                return $kpi;
            }
        }

        return null;
    }
}
