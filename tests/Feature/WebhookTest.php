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
function deliver(TestCase $case, array $body, ?string $signature = null): TestResponse
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $signature ??= (new SignatureVerifier('whsec-test'))->sign($raw);

    return $case->call(
        'POST',
        '/linqelio/webhook',
        server: ['HTTP_X_LINQELIO_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
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
    config()->set('linqelio.webhooks.tolerance', 60);

    deliver($this, payload(['occurredAt' => now()->subMinutes(10)->toIso8601String()]))
        ->assertStatus(401);
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
