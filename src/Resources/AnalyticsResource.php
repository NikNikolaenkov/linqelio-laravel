<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use DateTimeInterface;
use Linqelio\Laravel\Client\BinaryResponse;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Analytics\AnalyticsBreakdown;
use Linqelio\Laravel\Data\Analytics\AnalyticsExport;
use Linqelio\Laravel\Data\Analytics\AnalyticsExportRequest;
use Linqelio\Laravel\Data\Analytics\AnalyticsHeatmap;
use Linqelio\Laravel\Data\Analytics\AnalyticsOverview;
use Linqelio\Laravel\Data\Analytics\AnalyticsTimeseries;
use Linqelio\Laravel\Data\Analytics\CampaignReport;
use Linqelio\Laravel\Data\Enums\AnalyticsBreakdownBy;
use Linqelio\Laravel\Data\Enums\AnalyticsGranularity;
use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Enums\MessageDirection;
use Linqelio\Laravel\Data\Read;

/**
 * The cabinet's reports, for a dashboard of your own or a file to hand on.
 * Needs analytics:read.
 *
 * Days are calendar days in the cabinet's time zone: pass `YYYY-MM-DD` or any
 * DateTimeInterface (only its date is used). Omitted, the platform picks its
 * default range. A key scoped to some channels sees only theirs, and every
 * report says so in `scoped`.
 */
final readonly class AnalyticsResource
{
    public function __construct(private HttpClient $client) {}

    public function overview(
        DateTimeInterface|string|null $from = null,
        DateTimeInterface|string|null $to = null,
        ?string $channelId = null,
    ): AnalyticsOverview {
        return AnalyticsOverview::fromArray(
            $this->client->get('/analytics/overview', self::range($from, $to, $channelId))->data,
        );
    }

    public function timeseries(
        AnalyticsMetric $metric,
        ?AnalyticsGranularity $granularity = null,
        DateTimeInterface|string|null $from = null,
        DateTimeInterface|string|null $to = null,
        ?string $channelId = null,
    ): AnalyticsTimeseries {
        $query = ['metric' => $metric->value, 'granularity' => $granularity?->value] + self::range($from, $to, $channelId);

        return AnalyticsTimeseries::fromArray($this->client->get('/analytics/timeseries', Read::compact($query))->data);
    }

    public function breakdown(
        AnalyticsBreakdownBy $by,
        DateTimeInterface|string|null $from = null,
        DateTimeInterface|string|null $to = null,
        ?string $channelId = null,
    ): AnalyticsBreakdown {
        $query = ['by' => $by->value] + self::range($from, $to, $channelId);

        return AnalyticsBreakdown::fromArray($this->client->get('/analytics/breakdown', $query)->data);
    }

    /** Messages by weekday and hour of day. */
    public function heatmap(
        ?MessageDirection $direction = null,
        DateTimeInterface|string|null $from = null,
        DateTimeInterface|string|null $to = null,
        ?string $channelId = null,
    ): AnalyticsHeatmap {
        $query = Read::compact(['direction' => $direction?->value] + self::range($from, $to, $channelId));

        return AnalyticsHeatmap::fromArray($this->client->get('/analytics/heatmap', $query)->data);
    }

    /** A campaign's funnel: recipients, sent, delivered, read, replied. */
    public function campaign(string $campaignId): CampaignReport
    {
        return CampaignReport::fromArray($this->client->get("/analytics/campaigns/{$campaignId}")->data);
    }

    /**
     * Render a report into a CSV or XLSX file in the background:
     *
     *     Linqelio::analytics()->export(
     *         AnalyticsExportRequest::breakdown(AnalyticsBreakdownBy::Channel)->between('2026-09-01', '2026-09-30'),
     *     );
     */
    public function export(AnalyticsExportRequest $request): AnalyticsExport
    {
        return AnalyticsExport::fromArray($this->client->post('/analytics/exports', $request->toArray())->data);
    }

    /**
     * The key's recent report exports.
     *
     * @return array<int, AnalyticsExport>
     */
    public function exports(): array
    {
        return array_map(AnalyticsExport::fromArray(...), $this->client->get('/analytics/exports')->items());
    }

    public function findExport(string $exportId): AnalyticsExport
    {
        return AnalyticsExport::fromArray($this->client->get("/analytics/exports/{$exportId}")->data);
    }

    /**
     * The file. Before it is ready the answer is `analytics.export_not_ready`,
     * after it expired `analytics.export_expired` ({@see \Linqelio\Laravel\Exceptions\AnalyticsException}).
     */
    public function download(string $exportId): BinaryResponse
    {
        return $this->client->getRaw("/analytics/exports/{$exportId}/file");
    }

    /**
     * @return array<string, string>
     */
    private static function range(
        DateTimeInterface|string|null $from,
        DateTimeInterface|string|null $to,
        ?string $channelId,
    ): array {
        $query = [];

        foreach (['from' => Read::day($from), 'to' => Read::day($to), 'channelId' => $channelId] as $key => $value) {
            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
