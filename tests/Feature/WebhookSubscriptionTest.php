<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Facades\Linqelio;

it('lists subscriptions', function (): void {
    Http::fake([
        '*/webhooks' => Http::response([
            'items' => [
                ['id' => 'w-1', 'url' => 'https://host.test/hook', 'status' => 'active', 'eventTypes' => ['message.inbound']],
                ['id' => 'w-2', 'url' => 'https://host.test/other', 'status' => 'disabled'],
            ],
        ], 200),
    ]);

    $hooks = Linqelio::webhooks()->list();

    expect($hooks)->toHaveCount(2)
        ->and($hooks[0]->id)->toBe('w-1')
        ->and($hooks[0]->eventTypes)->toBe(['message.inbound'])
        ->and($hooks[0]->isActive())->toBeTrue()
        ->and($hooks[1]->isActive())->toBeFalse()
        // A subscription with no event types receives everything; that has to
        // read as an empty list, not as a missing field to guess at.
        ->and($hooks[1]->eventTypes)->toBe([]);
});

it('registers an endpoint against a secret reference', function (): void {
    Http::fake([
        '*' => Http::response(['id' => 'w-1', 'url' => 'https://host.test/hook', 'status' => 'active'], 201),
    ]);

    $registered = Linqelio::webhooks()->register('https://host.test/hook', ['message.inbound'], 'secret://webhooks/host');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $body['url'] === 'https://host.test/hook'
            && $body['eventTypes'] === ['message.inbound']
            && $body['secretRef'] === 'secret://webhooks/host'
            && ! array_key_exists('secret', $body);
    });

    // Nothing to hand back: the key is already in a store the caller controls.
    expect($registered->secret)->toBeNull()
        ->and($registered->mintedSecret())->toBeFalse()
        ->and($registered->id())->toBe('w-1');
});

// The registration that was impossible until the platform grew `secret`: the
// contract took only a `secret://` reference, and no API call could put a key
// behind one. An empty reference was the only reachable registration — and an
// empty reference is what makes the platform deliver UNSIGNED, which this
// package's own middleware rejects on arrival, every time.
it('registers with a key the caller already has, and does not echo it back', function (): void {
    Http::fake([
        '*' => Http::response(['id' => 'w-1', 'url' => 'https://host.test/hook', 'status' => 'active'], 201),
    ]);

    $registered = Linqelio::webhooks()->register(
        'https://host.test/hook',
        ['message.inbound'],
        secret: 'whsec_the_key_my_middleware_verifies_with',
    );

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $body['secret'] === 'whsec_the_key_my_middleware_verifies_with'
            && ! array_key_exists('secretRef', $body);
    });

    // Null because the caller supplied it: a key coming back would be a secret
    // in a response body and a proxy log, bought for nothing.
    expect($registered->secret)->toBeNull()
        ->and($registered->mintedSecret())->toBeFalse();
});

it('asks the platform to mint a key when given neither, and surfaces it once', function (): void {
    Http::fake([
        '*' => Http::response([
            'id' => 'w-1',
            'url' => 'https://host.test/hook',
            'status' => 'active',
            'secret' => 'whsec_minted_by_the_platform',
        ], 201),
    ]);

    $registered = Linqelio::webhooks()->register('https://host.test/hook');

    // Neither field on the wire: their joint absence IS the request to mint.
    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return ! array_key_exists('secret', $body) && ! array_key_exists('secretRef', $body);
    });

    expect($registered->secret)->toBe('whsec_minted_by_the_platform')
        ->and($registered->mintedSecret())->toBeTrue();
});

// Both would be two keys with no rule about which one signs; the platform answers
// 400. Refusing here spends no round trip on a request that cannot succeed.
it('refuses a key and a reference together', function (): void {
    Linqelio::webhooks()->register('https://host.test/hook', [], 'secret://webhooks/host', 'whsec_key');
})->throws(InvalidArgumentException::class);

// The dangerous confusion: sent as $secret, a `secret://…` string is not resolved
// — it is STORED, and every delivery is then signed with the literal text of a
// reference. The receiver verifies against the real key and rejects everything,
// which looks exactly like a platform fault.
it('refuses a reference passed as the key', function (): void {
    Linqelio::webhooks()->register('https://host.test/hook', [], secret: 'secret://webhooks/host');
})->throws(InvalidArgumentException::class);

// The listing's guarantee, stated as a type: Webhook has no key to carry, so no
// read can leak one no matter what the platform sends.
it('keeps the key off the type every read returns', function (): void {
    expect(property_exists(Linqelio\Laravel\Data\Webhook::class, 'secret'))->toBeFalse();
});

// Omitting the event types must mean "every type", which the contract expresses
// by leaving the field out — sending an empty array would ask for nothing.
it('omits event types entirely when none are given', function (): void {
    Http::fake(['*' => Http::response(['id' => 'w-1', 'url' => 'u', 'status' => 'active'], 201)]);

    Linqelio::webhooks()->register('https://host.test/hook');

    Http::assertSent(fn ($request): bool => ! array_key_exists('eventTypes', $request->data()));
});

// Disabling is the move when an endpoint breaks: the subscription and its
// signing key survive, so resuming does not mean handing out a new key.
it('disables and re-enables without touching the subscription', function (): void {
    Http::fake([
        '*/webhooks/w-1' => Http::sequence()
            ->push(['id' => 'w-1', 'url' => 'https://host.test/hook', 'status' => 'disabled'], 200)
            ->push(['id' => 'w-1', 'url' => 'https://host.test/hook', 'status' => 'active'], 200),
    ]);

    expect(Linqelio::webhooks()->disable('w-1')->isActive())->toBeFalse()
        ->and(Linqelio::webhooks()->enable('w-1')->isActive())->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && in_array($request->data()['status'], ['disabled', 'active'], true));
});

it('deletes a subscription', function (): void {
    Http::fake(['*/webhooks/w-1' => Http::response(null, 204)]);

    Linqelio::webhooks()->delete('w-1');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/webhooks/w-1'));
});

// Deleting is idempotent server-side, so a retry after a lost response answers
// 204 for an id that is already gone. It must not throw, or callers would treat
// a completed unsubscribe as a failure.
it('does not fail when deleting a subscription that is already gone', function (): void {
    Http::fake(['*' => Http::response(null, 204)]);

    Linqelio::webhooks()->delete('already-gone');
})->throwsNoExceptions();
