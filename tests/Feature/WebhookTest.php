<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Linqelio\Laravel\Events\MessageReceived;
use Linqelio\Laravel\Events\WebhookReceived;
use Linqelio\Laravel\Models\LinqelioMessage;
use Linqelio\Laravel\Tests\TestCase;
use Linqelio\Laravel\Webhooks\ProcessWebhook;
use Linqelio\Laravel\Webhooks\SignatureVerifier;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payload(array $overrides = []): array
{
    return array_merge([
        'messageId' => '01KZNES82TNQYBX6BK7V8SXB98',
        'kind' => 'tg_bot',
        'channelId' => 'ch-1',
        'chatId' => '629076487',
        'contactRef' => '629076487',
        'type' => 'text',
        'occurredAt' => now()->toIso8601String(),
    ], $overrides);
}

/**
 * Posts a delivery the way Linqelio would.
 *
 * Takes the TestCase explicitly rather than reaching for Pest's test() helper:
 * the raw body has to reach the middleware untouched, and passing the case in
 * keeps the call typed.
 *
 * @param  array<string, mixed>  $body
 */
function deliver(TestCase $case, array $body, ?string $signature = null, string $deliveryId = 'dl-1'): TestResponse
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $signature ??= (new SignatureVerifier('whsec-test'))->sign($raw);

    return $case->call(
        'POST',
        '/linqelio/webhook',
        server: [
            'HTTP_X_LINQELIO_SIGNATURE' => $signature,
            'HTTP_X_LINQELIO_DELIVERY' => $deliveryId,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $raw,
    );
}

/**
 * Posts a delivery the way a v2-signing platform would: the same body and v1
 * header, plus the per-attempt timestamp and the signature that covers it.
 *
 * @param  array<string, mixed>  $body
 */
function deliverV2(
    TestCase $case,
    array $body,
    string $sentAt = 'now',
    string $deliveryId = 'dl-1',
    ?string $signatureV2 = null,
): TestResponse {
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $verifier = new SignatureVerifier('whsec-test');
    $timestamp = now()->parse($sentAt)->utc()->format('Y-m-d\TH:i:s\Z');

    return $case->call(
        'POST',
        '/linqelio/webhook',
        server: [
            'HTTP_X_LINQELIO_SIGNATURE' => $verifier->sign($raw),
            'HTTP_X_LINQELIO_SIGNATURE_V2' => $signatureV2 ?? $verifier->signV2($timestamp, $deliveryId, $raw),
            'HTTP_X_LINQELIO_TIMESTAMP' => $timestamp,
            'HTTP_X_LINQELIO_DELIVERY' => $deliveryId,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $raw,
    );
}

it('accepts a correctly signed delivery', function (): void {
    Queue::fake();

    deliver($this, payload())->assertStatus(202);

    Queue::assertPushed(ProcessWebhook::class);
});

it('rejects a forged signature', function (): void {
    Queue::fake();

    deliver($this, payload(), 'sha256=deadbeef')->assertStatus(401);

    Queue::assertNothingPushed();
});

it('rejects a delivery with no signature at all', function (): void {
    deliver($this, payload(), '')->assertStatus(401);
});

it('rejects a replay older than the tolerance', function (): void {
    deliver($this, payload(['occurredAt' => now()->subHour()->toIso8601String()]))
        ->assertStatus(401);
});

it('accepts the last retry, which lands 930s after the event', function (): void {
    Queue::fake();

    // The deliverer makes 6 attempts backing off 30s and doubling, and the
    // payload's occurredAt is fixed at event time — so the final attempt arrives
    // 30+60+120+240+480 seconds old through no fault of anyone's. Rejecting it
    // dead-letters a delivery we ourselves refused.
    deliver($this, payload(['occurredAt' => now()->subSeconds(930)->toIso8601String()]))
        ->assertStatus(202);

    Queue::assertPushed(ProcessWebhook::class);
});

it('holds the tolerance floor when configured below the retry window', function (): void {
    Queue::fake();

    config()->set('linqelio.webhooks.tolerance', 300);

    deliver($this, payload(['occurredAt' => now()->subSeconds(930)->toIso8601String()]))
        ->assertStatus(202);

    Queue::assertPushed(ProcessWebhook::class);
});

it('does not let the delivery header decide what counts as a repeat', function (): void {
    Queue::fake();

    $body = payload();

    deliver($this, $body, deliveryId: 'dl-1')->assertStatus(202);
    // X-Linqelio-Delivery sits outside the signature — the MAC covers the body
    // alone — so a captured request can carry any value here. Keying single-use
    // on it would hand a replay a free pass.
    deliver($this, $body, deliveryId: 'dl-forged')->assertStatus(200);

    Queue::assertPushed(ProcessWebhook::class, 1);
});

it('accepts a v2-signed delivery', function (): void {
    Queue::fake();

    deliverV2($this, payload())->assertStatus(202);

    Queue::assertPushed(ProcessWebhook::class);
});

it('rejects a v2 signature that does not match', function (): void {
    Queue::fake();

    deliverV2($this, payload(), signatureV2: 'v2=deadbeef')->assertStatus(401);

    Queue::assertNothingPushed();
});

it('rejects a v2 delivery whose timestamp was swapped for a fresh one', function (): void {
    Queue::fake();

    $body = payload(['occurredAt' => now()->subDay()->toIso8601String()]);
    $raw = json_encode($body, JSON_THROW_ON_ERROR);

    // The capture was signed an hour ago. Restamping it defeats a receiver that
    // trusts the header — and is exactly what v2 exists to catch, because the
    // timestamp the attacker rewrote is the one inside the MAC.
    $stale = (new SignatureVerifier('whsec-test'))
        ->signV2(now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z'), 'dl-1', $raw);

    deliverV2($this, $body, signatureV2: $stale)->assertStatus(401);

    Queue::assertNothingPushed();
});

it('keeps a tight window on v2, because the stamp is per attempt', function (): void {
    Queue::fake();

    config()->set('linqelio.webhooks.tolerance', 300);

    // The event is ancient and the retry is recent — which is precisely the
    // shape of the platform's last attempt. Judged on occurredAt this is 401;
    // judged on the signed send time it is ordinary traffic.
    deliverV2($this, payload(['occurredAt' => now()->subSeconds(930)->toIso8601String()]))
        ->assertStatus(202);

    Queue::assertPushed(ProcessWebhook::class);
});

it('still ages out a v2 capture once its own stamp is old', function (): void {
    Queue::fake();

    config()->set('linqelio.webhooks.tolerance', 300);

    deliverV2($this, payload(), sentAt: now()->subSeconds(600)->toIso8601String())
        ->assertStatus(401);

    Queue::assertNothingPushed();
});

it('remembers a v2 delivery past the freshness window, so a late retry is not reprocessed', function (): void {
    Queue::fake();

    config()->set('linqelio.webhooks.tolerance', 300);

    deliverV2($this, payload())->assertStatus(202);

    // Same delivery, re-sent 930s later with a fresh stamp: fresh enough to pass
    // gate 2, so only a dedupe window wider than the tolerance stops it.
    deliverV2($this, payload())->assertStatus(200);

    Queue::assertPushed(ProcessWebhook::class, 1);
});

it('makes a delivery with no id of its own single-use as well', function (): void {
    Queue::fake();

    $body = ['event' => 'channel.reconnected', 'occurredAt' => now()->toIso8601String()];

    deliver($this, $body)->assertStatus(202);
    deliver($this, $body)->assertStatus(200);

    Queue::assertPushed(ProcessWebhook::class, 1);
});

it('acknowledges a repeat rather than processing it twice', function (): void {
    Queue::fake();

    $body = payload();

    deliver($this, $body)->assertStatus(202);
    // The platform retries deliveries; a repeat is legitimate traffic, so it is
    // acknowledged — answering with an error would keep it retrying forever.
    deliver($this, $body)->assertStatus(200);

    Queue::assertPushed(ProcessWebhook::class, 1);
});

it('signs over the raw body, so a re-encoding no longer verifies', function (): void {
    $verifier = new SignatureVerifier('whsec-test');

    // Same data, different bytes — a decode/encode round trip does not preserve
    // the sender's formatting. This is why the verifier must see the body as it
    // arrived rather than anything Laravel has already parsed.
    $raw = '{"a": 1, "b": [2, 3]}';
    $reencoded = json_encode(json_decode($raw, true), JSON_THROW_ON_ERROR);

    expect($reencoded)->not->toBe($raw)
        ->and($verifier->verify($reencoded, $verifier->sign($raw)))->toBeFalse()
        ->and($verifier->verify($raw, $verifier->sign($raw)))->toBeTrue();
});

it('refuses a signature that is right but unprefixed', function (): void {
    $verifier = new SignatureVerifier('whsec-test');
    $body = '{"a":1}';

    $bare = substr($verifier->sign($body), strlen('sha256='));

    expect($verifier->verify($body, $bare))->toBeFalse();
});

it('records the message in the projection and dispatches the typed event', function (): void {
    Event::fake([MessageReceived::class]);

    (new ProcessWebhook(payload()))->handle();

    expect(LinqelioMessage::query()->find('01KZNES82TNQYBX6BK7V8SXB98'))->not->toBeNull();

    Event::assertDispatched(MessageReceived::class);
});

it('leaves one row when the same delivery is processed twice', function (): void {
    Event::fake();

    (new ProcessWebhook(payload()))->handle();
    (new ProcessWebhook(payload()))->handle();

    expect(LinqelioMessage::query()->count())->toBe(1);
});

it('carries unrecognised deliveries through as a raw event', function (): void {
    Event::fake([WebhookReceived::class]);
    Queue::fake();

    deliver($this, ['event' => 'something.new', 'occurredAt' => now()->toIso8601String()])
        ->assertStatus(202);

    Event::assertDispatched(WebhookReceived::class);
});
