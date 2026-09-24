<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use DateTimeInterface;
use Linqelio\Laravel\Data\Enums\AnalyticsBreakdownBy;
use Linqelio\Laravel\Data\Enums\AnalyticsExportFormat;
use Linqelio\Laravel\Data\Enums\AnalyticsGranularity;
use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Enums\AnalyticsReportKind;
use Linqelio\Laravel\Data\Read;

/**
 * Which report to render into a file, with the same arguments the report takes.
 *
 * `metric` (and `granularity`) belong to a timeseries, `by` to a breakdown,
 * `campaignId` to a campaign report; the named constructors set the right one.
 */
final readonly class AnalyticsExportRequest
{
    public function __construct(
        public AnalyticsReportKind $report,
        public AnalyticsExportFormat $format = AnalyticsExportFormat::Csv,
        public DateTimeInterface|string|null $from = null,
        public DateTimeInterface|string|null $to = null,
        public ?string $channelId = null,
        public ?AnalyticsMetric $metric = null,
        public ?AnalyticsGranularity $granularity = null,
        public ?AnalyticsBreakdownBy $by = null,
        public ?string $campaignId = null,
    ) {}

    public static function overview(AnalyticsExportFormat $format = AnalyticsExportFormat::Csv): self
    {
        return new self(AnalyticsReportKind::Overview, $format);
    }

    public static function timeseries(
        AnalyticsMetric $metric,
        ?AnalyticsGranularity $granularity = null,
        AnalyticsExportFormat $format = AnalyticsExportFormat::Csv,
    ): self {
        return new self(AnalyticsReportKind::Timeseries, $format, metric: $metric, granularity: $granularity);
    }

    public static function breakdown(AnalyticsBreakdownBy $by, AnalyticsExportFormat $format = AnalyticsExportFormat::Csv): self
    {
        return new self(AnalyticsReportKind::Breakdown, $format, by: $by);
    }

    public static function campaign(string $campaignId, AnalyticsExportFormat $format = AnalyticsExportFormat::Csv): self
    {
        return new self(AnalyticsReportKind::Campaign, $format, campaignId: $campaignId);
    }

    /** The same request over other days, or narrowed to one channel. */
    public function between(
        DateTimeInterface|string|null $from,
        DateTimeInterface|string|null $to,
        ?string $channelId = null,
    ): self {
        return new self(
            report: $this->report,
            format: $this->format,
            from: $from,
            to: $to,
            channelId: $channelId ?? $this->channelId,
            metric: $this->metric,
            granularity: $this->granularity,
            by: $this->by,
            campaignId: $this->campaignId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            report: AnalyticsReportKind::tryFrom(Read::string($data, 'report')) ?? AnalyticsReportKind::Overview,
            format: AnalyticsExportFormat::tryFrom(Read::string($data, 'format')) ?? AnalyticsExportFormat::Csv,
            from: Read::stringOrNull($data, 'from'),
            to: Read::stringOrNull($data, 'to'),
            channelId: Read::stringOrNull($data, 'channelId'),
            metric: AnalyticsMetric::tryFrom(Read::string($data, 'metric')),
            granularity: AnalyticsGranularity::tryFrom(Read::string($data, 'granularity')),
            by: AnalyticsBreakdownBy::tryFrom(Read::string($data, 'by')),
            campaignId: Read::stringOrNull($data, 'campaignId'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Read::compact([
            'report' => $this->report->value,
            'format' => $this->format->value,
            'from' => Read::day($this->from),
            'to' => Read::day($this->to),
            'channelId' => $this->channelId,
            'metric' => $this->metric?->value,
            'granularity' => $this->granularity?->value,
            'by' => $this->by?->value,
            'campaignId' => $this->campaignId,
        ]);
    }
}
