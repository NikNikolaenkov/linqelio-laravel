<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Facades\Linqelio;

it('erases a contact and reports what it touched', function (): void {
    Http::fake([
        '*/contacts/c-1/erase' => Http::response([
            'contacts' => 1,
            'identities' => 3,
            'conversations' => 2,
            'messages' => 412,
        ], 200),
    ]);

    $result = Linqelio::contacts()->erase('c-1');

    expect($result->contacts)->toBe(1)
        ->and($result->identities)->toBe(3)
        ->and($result->conversations)->toBe(2)
        ->and($result->messages)->toBe(412)
        ->and($result->wasAlreadyErased())->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/contacts/c-1/erase'));
});

// The counts are the evidence the request was carried out. Recording "erased" is
// worth much less to a regulator than recording what was removed, so they must
// survive the round trip rather than being collapsed into a boolean.
it('keeps the counts so they can go in an erasure journal', function (): void {
    Http::fake([
        '*' => Http::response(['contacts' => 1, 'identities' => 2, 'conversations' => 0, 'messages' => 9], 200),
    ]);

    expect(Linqelio::contacts()->erase('c-1')->toArray())->toBe([
        'contacts' => 1,
        'identities' => 2,
        'conversations' => 0,
        'messages' => 9,
    ]);
});

// The erase is idempotent server-side: a retry after a timeout lands on someone
// already erased and answers 200 with zeros. That must read as success here, or
// callers would build retry logic that treats a completed erasure as a failure.
it('treats an already-erased subject as success, not an error', function (): void {
    Http::fake([
        '*' => Http::response(['contacts' => 0, 'identities' => 0, 'conversations' => 0, 'messages' => 0], 200),
    ]);

    $result = Linqelio::contacts()->erase('gone');

    expect($result->wasAlreadyErased())->toBeTrue()
        ->and($result->messages)->toBe(0);
});

it('reads a missing count as zero rather than failing', function (): void {
    Http::fake(['*' => Http::response(['contacts' => 1], 200)]);

    $result = Linqelio::contacts()->erase('c-1');

    expect($result->contacts)->toBe(1)
        ->and($result->messages)->toBe(0);
});
