<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Retiring a channel, and telling that apart from a replay of the same call.
 *
 * Deletion is the one channel operation that cannot be undone, so the answer
 * matters as much as the act: "the id named nothing" and "it took the channel
 * and 412 messages with it" are different facts, and once the call returns the
 * only places either survives are the platform's log and the caller's.
 */
it('reports what the deletion removed', function (): void {
    Http::fake(['*/channels/ch-1' => Http::response([
        'deleted' => true,
        'conversations' => 12,
        'messages' => 412,
        'outbox' => 3,
        'media' => 7,
        'deskInboxRemoved' => true,
    ])]);

    $result = Linqelio::channels()->delete('ch-1');

    expect($result->deleted)->toBeTrue()
        ->and($result->messages)->toBe(412)
        ->and($result->outbox)->toBe(3)
        ->and($result->deskInboxRemoved)->toBeTrue()
        ->and($result->wasAlreadyDeleted())->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/channels/ch-1'));
});

/*
 * An id that names nothing is a SUCCESS, not a 404 to handle: the delete is
 * idempotent, so a retry after a lost response lands here and the requested
 * state still holds. A caller that treated this as failure would keep retrying
 * a deletion that already happened.
 */
it('treats a deletion of nothing as a successful replay', function (): void {
    Http::fake(['*/channels/gone' => Http::response([
        'deleted' => false,
        'conversations' => 0,
        'messages' => 0,
        'outbox' => 0,
        'media' => 0,
        'deskInboxRemoved' => false,
    ])]);

    $result = Linqelio::channels()->delete('gone');

    expect($result->wasAlreadyDeleted())->toBeTrue()
        ->and($result->deleted)->toBeFalse();
});
