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

it('registers an endpoint with a secret reference, never a key', function (): void {
    Http::fake([
        '*' => Http::response(['id' => 'w-1', 'url' => 'https://host.test/hook', 'status' => 'active'], 201),
    ]);

    Linqelio::webhooks()->register('https://host.test/hook', ['message.inbound'], 'secret://webhooks/host');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $body['url'] === 'https://host.test/hook'
            && $body['eventTypes'] === ['message.inbound']
            && $body['secretRef'] === 'secret://webhooks/host';
    });
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
