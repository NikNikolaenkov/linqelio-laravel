<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Linqelio\Laravel\Data\Enums\MessageStatus;
use Linqelio\Laravel\Events\MessageReceived;
use Linqelio\Laravel\Events\MessageStatusChanged;
use Linqelio\Laravel\Facades\Linqelio;
use Linqelio\Laravel\Models\LinqelioMessage;
use Linqelio\Laravel\Tests\TestCase;
use Linqelio\Laravel\Webhooks\ProcessWebhook;
use Linqelio\Laravel\Webhooks\SignatureVerifier;

/**
 * A send is accepted, not delivered: `send()` returns as soon as the platform
 * takes the command, and the provider is reached afterwards. Until the platform
 * grew `message.status` and `GET /messages/{id}`, the outcome was unreachable —
 * a failed send looked exactly like a successful one.
 */

/**
 * @param  array<string, mixed>  $body
 */
function deliverEvent(TestCase $case, array $body, string $eventType): TestResponse
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);

    return $case->call(
        'POST',
        '/linqelio/webhook',
        server: [
            'HTTP_X_LINQELIO_SIGNATURE' => (new SignatureVerifier('whsec-test'))->sign($raw),
            'HTTP_X_LINQELIO_EVENT' => $eventType,
            'HTTP_X_LINQELIO_DELIVERY' => 'dl-status-1',
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $raw,
    );
}

/**
 * @return array<string, mixed>
 */
function statusPayload(array $overrides = []): array
{
    return array_merge([
        'messageId' => '01OUT',
        'status' => 'failed',
        'kind' => 'wa_web',
        'channelId' => 'ch-1',
        'chatId' => '380500000000@s.whatsapp.net',
        'contactRef' => '380500000000@s.whatsapp.net',
        'providerMsgId' => 'wamid.X',
        'reason' => 'recipient is not on WhatsApp',
        'occurredAt' => now()->toIso8601String(),
    ], $overrides);
}

it('reads one message and its status', function (): void {
    Http::fake(['*' => Http::response([
        'id' => '01OUT', 'direction' => 'outbound', 'type' => 'text',
        'content' => ['text' => 'hi'], 'status' => 'failed',
        'meta' => ['error' => 'recipient is not on WhatsApp'],
        'timestamps' => ['created' => '2026-01-01T10:00:00Z'],
    ], 200)]);

    $message = Linqelio::messages()->find('01OUT');

    expect($message->id)->toBe('01OUT')
        ->and($message->status)->toBe(MessageStatus::Failed);
    // "failed" is a status; the reason is the answer. Without it an operator is
    // back to reading platform logs they have no access to.
    expect($message->failureReason())->toBe('recipient is not on WhatsApp');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/messages/01OUT'));
});

it('reports no failure reason for a message that did not fail', function (): void {
    Http::fake(['*' => Http::response([
        'id' => '01OUT', 'type' => 'text', 'status' => 'delivered',
        'meta' => ['error' => 'stale value from an earlier attempt'],
    ], 200)]);

    expect(Linqelio::messages()->find('01OUT')->failureReason())->toBeNull();
});

it('turns a message.status delivery into a typed event', function (): void {
    Event::fake([MessageStatusChanged::class]);

    (new ProcessWebhook(statusPayload(), 'message.status'))->handle();

    Event::assertDispatched(MessageStatusChanged::class, function (MessageStatusChanged $e): bool {
        return $e->messageId === '01OUT'
            && $e->status === MessageStatus::Failed
            && $e->reason === 'recipient is not on WhatsApp'
            && $e->hasFailed();
    });
});

// Both message events carry `messageId`, so the delivery has to be identified by
// the platform's own header rather than by which other fields happen to be
// present — a guess that breaks the first time a payload grows a field.
it('routes by the event header, not by the payload shape', function (): void {
    Event::fake([MessageStatusChanged::class, MessageReceived::class]);

    (new ProcessWebhook(statusPayload(), 'message.status'))->handle();

    Event::assertDispatched(MessageStatusChanged::class);
    Event::assertNotDispatched(MessageReceived::class);
});

it('still recognises a status delivery from a platform with no event header', function (): void {
    Event::fake([MessageStatusChanged::class]);

    (new ProcessWebhook(statusPayload(), ''))->handle();

    Event::assertDispatched(MessageStatusChanged::class);
});

it('treats a delivery with no status as inbound, as before', function (): void {
    Event::fake([MessageReceived::class, MessageStatusChanged::class]);

    (new ProcessWebhook([
        'messageId' => '01IN', 'kind' => 'tg_bot', 'channelId' => 'ch-1',
        'chatId' => '42', 'contactRef' => '42', 'type' => 'text',
        'occurredAt' => now()->toIso8601String(),
    ], ''))->handle();

    Event::assertDispatched(MessageReceived::class);
    Event::assertNotDispatched(MessageStatusChanged::class);
});

it('carries the event header through the webhook route', function (): void {
    Queue::fake();

    deliverEvent($this, statusPayload(), 'message.status')->assertStatus(202);

    Queue::assertPushed(ProcessWebhook::class);
});

it('advances the projection without blanking the body it already holds', function (): void {
    Event::fake();

    // A send this application recorded, with its content.
    LinqelioMessage::query()->create([
        'id' => '01OUT', 'channel_id' => 'ch-1', 'kind' => 'wa_web',
        'direction' => 'outbound', 'type' => 'text', 'status' => 'queued',
        'chat_id' => '380500000000@s.whatsapp.net',
        'contact_ref' => '380500000000@s.whatsapp.net',
        'content' => ['text' => 'your appointment is confirmed'],
        'text' => 'your appointment is confirmed',
        'occurred_at' => now(),
    ]);

    (new ProcessWebhook(statusPayload(), 'message.status'))->handle();

    $row = LinqelioMessage::query()->find('01OUT');
    expect($row->status)->toBe('failed')
        ->and($row->provider_message_id)->toBe('wamid.X');
    // The status payload carries no body. Rewriting the row from it would erase
    // the content this projection exists to hold.
    expect($row->text)->toBe('your appointment is confirmed');
});

// A status can arrive for a message this application never recorded — a send made
// from elsewhere, or a projection switched on afterwards. A row saying "this
// failed" with no body still beats no row at all.
it('creates a row for a status it has never seen before', function (): void {
    Event::fake();

    (new ProcessWebhook(statusPayload(), 'message.status'))->handle();

    $row = LinqelioMessage::query()->find('01OUT');
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('failed')
        ->and($row->direction)->toBe('outbound')
        ->and($row->chat_id)->toBe('380500000000@s.whatsapp.net');
});
