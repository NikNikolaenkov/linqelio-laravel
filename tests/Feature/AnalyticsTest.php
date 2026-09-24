<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Analytics\AnalyticsExportRequest;
use Linqelio\Laravel\Data\Enums\AnalyticsBreakdownBy;
use Linqelio\Laravel\Data\Enums\AnalyticsExportFormat;
use Linqelio\Laravel\Data\Enums\AnalyticsGranularity;
use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Enums\AnalyticsUnit;
use Linqelio\Laravel\Data\Enums\ExportStatus;
use Linqelio\Laravel\Data\Enums\MessageDirection;
use Linqelio\Laravel\Exceptions\AnalyticsException;
use Linqelio\Laravel\Facades\Linqelio;

function analyticsPeriod(): array
{
    return ['from' => '2026-09-01', 'to' => '2026-09-30', 'timeZone' => 'Europe/Kyiv'];
}

it('reads the overview, with a missing value kept apart from zero', function (): void {
    Http::fake(['*' => Http::response([
        'period' => analyticsPeriod(), 'previousPeriod' => analyticsPeriod(), 'scoped' => false,
        'kpis' => [
            ['metric' => 'messages_out', 'unit' => 'count', 'value' => 120, 'previous' => 100, 'delta' => 20, 'deltaPct' => 0.2],
            ['metric' => 'read_rate', 'unit' => 'ratio'],
        ],
        'freshness' => ['lastPassAt' => '2026-09-24T09:55:00Z', 'backfill' => 'running', 'backfillProgress' => 0.4],
    ])]);

    $overview = Linqelio::analytics()->overview(new DateTimeImmutable('2026-09-01'), '2026-09-30', 'ch-1');

    expect($overview->kpi(AnalyticsMetric::MessagesOut)?->value)->toBe(120.0)
        ->and($overview->kpi(AnalyticsMetric::MessagesOut)?->unit)->toBe(AnalyticsUnit::Count)
        ->and($overview->kpi(AnalyticsMetric::ReadRate)?->value)->toBeNull()
        ->and($overview->backfill)->toBe('running')
        ->and($overview->period->timeZone)->toBe('Europe/Kyiv');

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/analytics/overview?')
        && $r['from'] === '2026-09-01' && $r['to'] === '2026-09-30' && $r['channelId'] === 'ch-1');
});

it('shapes each report query as the contract names it', function (Closure $call, string $path, array $query): void {
    Http::fake(['*' => Http::response(['period' => analyticsPeriod()])]);

    $call();

    Http::assertSent(function (Request $r) use ($path, $query): bool {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $sent);

        return str_contains($r->url(), $path) && $sent == $query;
    });
})->with([
    'timeseries' => [fn () => Linqelio::analytics()->timeseries(AnalyticsMetric::DeliveryRate, AnalyticsGranularity::Week), '/analytics/timeseries', ['metric' => 'delivery_rate', 'granularity' => 'week']],
    'breakdown' => [fn () => Linqelio::analytics()->breakdown(AnalyticsBreakdownBy::Agent, '2026-09-01'), '/analytics/breakdown', ['by' => 'agent', 'from' => '2026-09-01']],
    'heatmap' => [fn () => Linqelio::analytics()->heatmap(MessageDirection::Inbound), '/analytics/heatmap', ['direction' => 'inbound']],
    'campaign' => [fn () => Linqelio::analytics()->campaign('cmp-1'), '/analytics/campaigns/cmp-1', []],
]);

it('reads breakdown rows, heatmap cells and a campaign funnel', function (): void {
    Http::fake([
        '*/breakdown*' => Http::response(['by' => 'channel', 'period' => analyticsPeriod(), 'metrics' => ['messages_out', 'new_metric'], 'rows' => [['key' => 'ch-1', 'label' => 'Shop', 'values' => ['messages_out' => 7]]]]),
        '*/heatmap*' => Http::response(['direction' => 'inbound', 'period' => analyticsPeriod(), 'cells' => [[0, 1], [2, 3]], 'max' => 3]),
        '*/campaigns/*' => Http::response(['campaignId' => 'cmp-1', 'name' => 'A', 'status' => 'completed', 'funnel' => ['recipients' => 10, 'delivered' => 8, 'replied' => 2, 'replyWindowHours' => 72]]),
    ]);

    $breakdown = Linqelio::analytics()->breakdown(AnalyticsBreakdownBy::Channel);
    $heatmap = Linqelio::analytics()->heatmap();
    $report = Linqelio::analytics()->campaign('cmp-1');

    expect($breakdown->metrics)->toBe([AnalyticsMetric::MessagesOut])
        ->and($breakdown->rows[0]['values'])->toBe(['messages_out' => 7.0])
        ->and($heatmap->cells)->toBe([[0, 1], [2, 3]])
        ->and($report->funnel['delivered'])->toBe(8)
        ->and($report->funnel['replyWindowHours'])->toBe(72);
});

it('exports a report and downloads the file', function (): void {
    Http::fake([
        '*/file' => Http::response('xlsx-bytes', 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
        '*/analytics/exports' => Http::response(['id' => 'ax-1', 'status' => 'completed', 'request' => ['report' => 'breakdown', 'format' => 'xlsx', 'by' => 'channel']], 202),
    ]);

    $export = Linqelio::analytics()->export(
        AnalyticsExportRequest::breakdown(AnalyticsBreakdownBy::Channel, AnalyticsExportFormat::Xlsx)->between('2026-09-01', '2026-09-30'),
    );
    $file = Linqelio::analytics()->download('ax-1');

    expect($export->status)->toBe(ExportStatus::Completed)
        ->and($export->request->by)->toBe(AnalyticsBreakdownBy::Channel)
        ->and($file->bytes)->toBe('xlsx-bytes');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r->data() === [
        'report' => 'breakdown', 'format' => 'xlsx', 'from' => '2026-09-01', 'to' => '2026-09-30', 'by' => 'channel',
    ]);
});

it('maps an export that is not ready to an AnalyticsException', function (): void {
    Http::fake(['*' => Http::response(['code' => 'analytics.export_not_ready', 'detail' => 'wait'], 409)]);

    expect(fn () => Linqelio::analytics()->download('ax-1'))->toThrow(AnalyticsException::class);
});
