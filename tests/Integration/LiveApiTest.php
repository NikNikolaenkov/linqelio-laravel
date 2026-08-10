<?php

declare(strict_types=1);

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Exceptions\AuthException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Runs against a REAL Linqelio installation.
 *
 * Everything else in this suite talks to Http::fake(), which proves the package
 * behaves as written but not that what it was written against is true. These
 * tests close that gap: they exercise the actual wire format, the actual error
 * bodies, and the actual auth.
 *
 * Read-only by design. Sending a message would reach a real person on a real
 * phone, so nothing here does — the send path stays covered by the faked tests
 * and by acceptance runs a human decides to make.
 *
 * Skipped unless both are set:
 *
 *   LINQELIO_LIVE_URL=https://your-host/v1 \
 *   LINQELIO_LIVE_KEY=<cabinetId>.<keyId>.<secret> \
 *     vendor/bin/pest --group=live
 */
beforeEach(function (): void {
    $url = getenv('LINQELIO_LIVE_URL');
    $key = getenv('LINQELIO_LIVE_KEY');

    if ($url === false || $key === false || $url === '' || $key === '') {
        $this->markTestSkipped('LINQELIO_LIVE_URL / LINQELIO_LIVE_KEY not set');
    }

    config()->set('linqelio.base_url', $url);
    config()->set('linqelio.key', $key);

    // Rebuild the client so it picks the live configuration up.
    $this->app->forgetInstance(HttpClient::class);
    $this->app->forgetInstance(\Linqelio\Laravel\Linqelio::class);
});

it('authenticates and lists channels', function (): void {
    $channels = Linqelio::channels()->list();

    expect($channels)->not->toBeEmpty();

    foreach ($channels as $channel) {
        expect($channel->id)->not->toBe('')
            ->and($channel->kind)->toBeInstanceOf(ChannelKind::class);
    }
})->group('live');

it('reads a channel status', function (): void {
    $channel = Linqelio::channels()->list()[0];

    expect(Linqelio::channels()->status($channel->id))->toBeArray();
})->group('live');

it('pages through contacts and maps their identities', function (): void {
    $page = Linqelio::contacts()->list(limit: 5);

    expect($page['contacts'])->not->toBeEmpty();

    $contact = $page['contacts'][0];

    expect($contact->id)->not->toBe('')
        ->and($contact->cabinetId)->not->toBe('')
        ->and($contact->identities)->not->toBeEmpty();
})->group('live');

it('finds a contact by the provider address a webhook would carry', function (): void {
    $contact = Linqelio::contacts()->list(limit: 1)['contacts'][0];
    $identity = $contact->identities[0];

    // This is the lookup MessageReceived::contact() depends on — a webhook names
    // the other party the way the channel does, not by our contact id.
    $found = Linqelio::contacts()->findByIdentity($identity->providerId, $identity->channelType);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($contact->id);
})->group('live');

it('reads a conversation feed', function (): void {
    $conversations = Linqelio::conversations()->list(limit: 5)['conversations'];

    expect($conversations)->not->toBeEmpty();

    $feed = Linqelio::conversations()->feed((string) $conversations[0]['id'], limit: 5);

    expect($feed['messages'])->toBeArray();
})->group('live');

it('uploads an attachment and gets a fetchable URL back', function (): void {
    // A 1x1 PNG. Writes to the platform's object store and nothing else — no
    // message is sent, so nobody receives it.
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );

    $media = Linqelio::media()->upload($png, filename: 'package-live-test.png', mime: 'image/png');

    expect($media->url)->toStartWith('http')
        ->and($media->size)->toBe(strlen($png))
        ->and($media->mime)->toBe('image/png');
})->group('live');

it('reports a bad key as an auth error rather than something generic', function (): void {
    config()->set('linqelio.key', 'cab-1.k-nope.wrong');
    $this->app->forgetInstance(HttpClient::class);
    $this->app->forgetInstance(\Linqelio\Laravel\Linqelio::class);

    expect(fn () => Linqelio::channels()->list())->toThrow(AuthException::class);
})->group('live');
