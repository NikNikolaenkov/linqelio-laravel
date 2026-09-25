<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\AlertDeliveryChannel;
use Linqelio\Laravel\Data\Enums\AlertSeverity;
use Linqelio\Laravel\Data\Enums\AlertState;
use Linqelio\Laravel\Data\Enums\AlertStatus;
use Linqelio\Laravel\Data\Enums\AlertType;
use Linqelio\Laravel\Data\Enums\HealthRating;
use Linqelio\Laravel\Exceptions\AlertException;
use Linqelio\Laravel\Facades\Linqelio;

function alertBody(string $type = 'channel.disconnected'): array
{
    return [
        'id' => 'al-1', 'type' => $type, 'severity' => 'critical', 'status' => 'open', 'channelId' => 'ch-1',
        'value' => 1, 'params' => ['minutes' => 12], 'occurrence' => 3,
        'raisedAt' => '2026-09-24T10:00:00Z', 'lastSeenAt' => '2026-09-24T10:12:00Z', 'autoResolved' => false,
    ];
}

it('reads channel health with its explanation and history', function (): void {
    Http::fake([
        '*/channel-health/ch-1/history*' => Http::response(['channelId' => 'ch-1', 'items' => [
            ['at' => '2026-09-23T00:00:00Z', 'score' => 90, 'rating' => 'good', 'connected' => true, 'reasons' => []],
        ]]),
        '*/channel-health/ch-1' => Http::response([
            'channelId' => 'ch-1', 'kind' => 'wa_web', 'computed' => true, 'score' => 61, 'rating' => 'warning', 'connected' => true,
            'components' => [['key' => 'failures', 'weight' => 30, 'score' => 40, 'lost' => 18.0, 'reason' => 'failure_rate', 'insufficientData' => false, 'params' => ['rate' => 0.12]]],
            'reasons' => ['failure_rate'],
        ]),
        '*/channel-health' => Http::response(['items' => [['channelId' => 'ch-2', 'rating' => 'brand-new']]]),
    ]);

    $health = Linqelio::health()->find('ch-1');
    $history = Linqelio::health()->history('ch-1', new DateTimeImmutable('2026-09-01T00:00:00+00:00'), 30);
    $all = Linqelio::health()->list();

    expect($health->rating)->toBe(HealthRating::Warning)
        ->and($health->needsAttention())->toBeTrue()
        ->and($health->components[0]->params)->toBe(['rate' => 0.12])
        ->and($history[0]->score)->toBe(90)
        // A rating newer than the package is "unknown", never "good".
        ->and($all[0]->rating)->toBe(HealthRating::Unknown);

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/channel-health/ch-1/history')
        && $r['since'] === '2026-09-01T00:00:00+00:00' && (int) $r['limit'] === 30);
});

it('pages alerts like every other list: `since` out, `pageInfo.nextCursor` back', function (): void {
    Http::fake(['*/alerts*' => Http::response([
        'items' => [alertBody(), alertBody('channel.tomorrow')],
        'pageInfo' => ['nextCursor' => 'a-2', 'hasMore' => true],
        // The deprecated copy; a newer platform may stop sending it.
        'nextCursor' => 'a-2-legacy',
    ])]);

    $page = Linqelio::alerts()->list(AlertStatus::Open, 'ch-1', 'a-1', 20);

    expect($page['alerts'][0]->type)->toBe(AlertType::ChannelDisconnected)
        ->and($page['alerts'][0]->severity)->toBe(AlertSeverity::Critical)
        ->and($page['alerts'][0]->occurrence)->toBe(3)
        ->and($page['alerts'][0]->isActive())->toBeTrue()
        ->and($page['alerts'][1]->type)->toBeNull()
        ->and($page['alerts'][1]->typeValue)->toBe('channel.tomorrow')
        ->and($page['nextCursor'])->toBe('a-2');

    Http::assertSent(fn (Request $r): bool => $r['since'] === 'a-1'
        && ! isset($r['cursor'])
        && $r['status'] === 'open'
        && $r['channelId'] === 'ch-1');
});

it('still reads the top-level `nextCursor` of a platform older than issue #140', function (): void {
    Http::fake(['*' => Http::response(['items' => [alertBody()], 'nextCursor' => 'old-2'])]);

    expect(Linqelio::alerts()->list()['nextCursor'])->toBe('old-2');
});

it('filters on state and time, and turns the deprecated `active` status into `state`', function (): void {
    Http::fake(['*' => Http::response(['items' => [], 'pageInfo' => []])]);

    Linqelio::alerts()->list(state: AlertState::Active, before: new DateTimeImmutable('2026-09-25T12:00:00+00:00'));
    Linqelio::alerts()->list('active');
    Linqelio::alerts()->list('resolved', state: AlertState::All);

    $queries = Http::recorded()->map(function (array $pair): array {
        parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $query);

        return $query;
    })->all();

    expect($queries[0])->toBe(['state' => 'active', 'before' => '2026-09-25T12:00:00+00:00'])
        ->and($queries[1])->toBe(['state' => 'active'])
        ->and($queries[2])->toBe(['status' => 'resolved', 'state' => 'all']);
});

it('refuses a status string that is no alert status', function (): void {
    Http::fake();

    expect(fn () => Linqelio::alerts()->list('closed'))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('acknowledges and resolves idempotently', function (): void {
    Http::fake(['*' => Http::response(['alert' => alertBody(), 'changed' => false])]);

    expect(Linqelio::alerts()->acknowledge('al-1')->changed)->toBeFalse()
        ->and(Linqelio::alerts()->resolve('al-1')->alert->id)->toBe('al-1');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && str_ends_with($r->url(), '/alerts/al-1/acknowledge'));
    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && str_ends_with($r->url(), '/alerts/al-1/resolve'));
});

it('routes alerts to a webhook and changes the subscription', function (): void {
    Http::fake(['*' => Http::response([
        'id' => 'sub-1', 'webhookId' => 'wh-1', 'channel' => 'webhook', 'minSeverity' => 'warning',
        'ruleTypes' => ['channel.disconnected', 'channel.someday'], 'channelIds' => [], 'enabled' => true, 'own' => false,
    ], 201)]);

    $sub = Linqelio::alerts()->subscribe(AlertDeliveryChannel::Webhook, webhookId: 'wh-1', minSeverity: AlertSeverity::Warning, ruleTypes: [AlertType::ChannelDisconnected]);
    Linqelio::alerts()->updateSubscription('sub-1', enabled: false, channelIds: []);
    Linqelio::alerts()->unsubscribe('sub-1');

    expect($sub->channel)->toBe(AlertDeliveryChannel::Webhook)
        ->and($sub->ruleTypes)->toBe([AlertType::ChannelDisconnected]);

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r->data() === [
        'channel' => 'webhook', 'webhookId' => 'wh-1', 'minSeverity' => 'warning', 'ruleTypes' => ['channel.disconnected'],
    ]);
    Http::assertSent(fn (Request $r): bool => $r->method() === 'PATCH' && $r->data() === ['channelIds' => [], 'enabled' => false]);
    Http::assertSent(fn (Request $r): bool => $r->method() === 'DELETE' && str_ends_with($r->url(), '/alert-subscriptions/sub-1'));
});

it('maps a duplicate subscription to an AlertException', function (): void {
    Http::fake(['*' => Http::response(['code' => 'alert.subscription_conflict', 'detail' => 'exists'], 409)]);

    expect(fn () => Linqelio::alerts()->subscribe(AlertDeliveryChannel::Webhook, webhookId: 'wh-1'))
        ->toThrow(AlertException::class);
});
