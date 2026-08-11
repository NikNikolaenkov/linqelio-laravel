<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * One process, several cabinets.
 *
 * The container binds a single HttpClient built from `linqelio.key`. Under a
 * persistent worker that instance outlives the request that resolved it, so the
 * key has to travel with the call rather than sit on the shared object.
 */
it('sends another cabinet key without touching the shared client', function (): void {
    Http::fake(['*' => Http::response(['items' => []], 200)]);

    Linqelio::forKey('cab-2.k-other.secret')->contacts()->list();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer cab-2.k-other.secret'));

    // The singleton is unchanged: the next caller gets the configured cabinet,
    // not whichever one happened to run last.
    Linqelio::contacts()->list();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer cab-1.k-test.secret'));
});

it('leaves the container binding alone', function (): void {
    $shared = app(HttpClient::class);

    $other = $shared->withKey('cab-2.k-other.secret');

    expect($other)->not->toBe($shared)
        ->and(app(HttpClient::class))->toBe($shared);
});

it('carries the rest of the configuration across', function (): void {
    config()->set('linqelio.base_url', 'https://tenant.test/v1');

    Http::fake(['*' => Http::response(['items' => []], 200)]);

    Linqelio::forKey('cab-2.k-other.secret')->contacts()->list();

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://tenant.test/v1/contacts'));
});
