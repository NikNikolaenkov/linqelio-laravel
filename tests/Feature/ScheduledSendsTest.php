<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Enums\ScheduledSendStatus;
use Linqelio\Laravel\Exceptions\CampaignException;
use Linqelio\Laravel\Facades\Linqelio;

function scheduledBody(string $status = 'scheduled'): array
{
    return [
        'id' => 's-1',
        'contactId' => 'c-1',
        'channelId' => 'ch-1',
        'status' => $status,
        'sendAt' => '2026-10-01T06:00:00Z',
        'nextAttemptAt' => '2026-10-01T06:00:00Z',
        'type' => 'text',
        'content' => ['text' => 'reminder'],
        'attempts' => 0,
        'createdBy' => ['type' => 'api_key', 'id' => 'k-1'],
        'createdAt' => '2026-09-24T10:00:00Z',
        'updatedAt' => '2026-09-24T10:00:00Z',
    ];
}

it('schedules a send with a pinned idempotency key and an RFC 3339 moment', function (): void {
    Http::fake(['*' => Http::response(scheduledBody(), 201)]);

    $scheduled = Linqelio::scheduledSends()->create(
        'c-1',
        new DateTimeImmutable('2026-10-01T09:00:00+03:00'),
        MessageType::Text,
        ['text' => 'reminder'],
        channelId: 'ch-1',
        idempotencyKey: 'booking-7-reminder',
    );

    expect($scheduled->status)->toBe(ScheduledSendStatus::Scheduled)
        ->and($scheduled->status->isPending())->toBeTrue()
        ->and($scheduled->content)->toBe(['text' => 'reminder'])
        ->and($scheduled->createdBy?->id)->toBe('k-1');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/scheduled-sends')
        && $r->header('Idempotency-Key')[0] === 'booking-7-reminder'
        && $r->data() === [
            'contactId' => 'c-1',
            'channelId' => 'ch-1',
            'sendAt' => '2026-10-01T09:00:00+03:00',
            'type' => 'text',
            'content' => ['text' => 'reminder'],
        ]);
});

it('lists, moves and cancels', function (): void {
    Http::fake([
        '*/scheduled-sends/s-1/cancel' => Http::response(scheduledBody('cancelled')),
        '*/scheduled-sends/s-1' => Http::response(scheduledBody()),
        '*/scheduled-sends*' => Http::response(['items' => [scheduledBody()], 'pageInfo' => ['nextCursor' => 'n-2']]),
    ]);

    $page = Linqelio::scheduledSends()->list('c-1', ScheduledSendStatus::Scheduled, 'n-1', 10);
    Linqelio::scheduledSends()->update('s-1', new DateTimeImmutable('2026-10-02T09:00:00+00:00'));
    $cancelled = Linqelio::scheduledSends()->cancel('s-1');

    expect($page['scheduledSends'])->toHaveCount(1)
        ->and($page['nextCursor'])->toBe('n-2')
        ->and($cancelled->status->isTerminal())->toBeTrue();

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
        && ($r['since'] ?? null) === 'n-1' && $r['contactId'] === 'c-1' && $r['status'] === 'scheduled');
    Http::assertSent(fn (Request $r): bool => $r->method() === 'PATCH'
        && $r->data() === ['sendAt' => '2026-10-02T09:00:00+00:00']);
    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && str_ends_with($r->url(), '/scheduled-sends/s-1/cancel'));
});

it('maps a send that already went to a CampaignException family', function (): void {
    Http::fake(['*' => Http::response(['code' => 'scheduled_send.state_conflict', 'detail' => 'already sent'], 409)]);

    expect(fn () => Linqelio::scheduledSends()->cancel('s-1'))->toThrow(CampaignException::class);
});
