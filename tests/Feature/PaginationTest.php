<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Reading pages: the field the body arrives under, and the query parameter that
 * walks to the next one.
 *
 * Neither is checked by the contract parity gate, which compares operation ids
 * and error codes and nothing about field names. Both failed silently — an empty
 * page and a first page forever look exactly like a quiet account.
 */

/**
 * @param  array<string, mixed>  $body
 */
function page(array $body): void
{
    Http::fake(['*' => Http::response($body, 200)]);
}

it('reads a contact history out of `messages`, not `items`', function (): void {
    page([
        'contactId' => 'c-1',
        'messages' => [
            ['id' => '01A', 'type' => 'text', 'content' => ['text' => 'first']],
            ['id' => '01B', 'type' => 'text', 'content' => ['text' => 'second']],
        ],
        'pageInfo' => ['nextCursor' => 'cur-2'],
    ]);

    $history = Linqelio::messages()->history('c-1');

    expect($history['messages'])->toHaveCount(2)
        ->and($history['messages'][0]->id)->toBe('01A')
        ->and($history['nextCursor'])->toBe('cur-2');
});

it('reads a conversation feed out of `messages`, not `items`', function (): void {
    page([
        'conversationId' => 'cv-1',
        'contactId' => 'c-1',
        'messages' => [['id' => '01A', 'type' => 'text', 'content' => ['text' => 'hi']]],
    ]);

    $feed = Linqelio::conversations()->feed('cv-1');

    expect($feed['messages'])->toHaveCount(1)
        ->and($feed['messages'][0]->id)->toBe('01A');
});

it('still reads conversation and contact lists out of `items`', function (): void {
    page(['items' => [['id' => 'cv-1', 'channelId' => 'ch-1', 'chatId' => '42']]]);
    expect(Linqelio::conversations()->list()['conversations'])->toHaveCount(1);

    page(['items' => [['id' => 'c-1', 'identities' => []]]]);
    expect(Linqelio::contacts()->list()['contacts'])->toHaveCount(1);
});

it('walks a contact history with `before`', function (): void {
    page(['contactId' => 'c-1', 'messages' => []]);

    Linqelio::messages()->history('c-1', cursor: 'cur-2', limit: 50);

    Http::assertSent(fn ($request): bool => $request['before'] === 'cur-2'
        && ! isset($request['cursor']));
});

it('walks a conversation feed with `before`', function (): void {
    page(['conversationId' => 'cv-1', 'contactId' => 'c-1', 'messages' => []]);

    Linqelio::conversations()->feed('cv-1', before: 'cur-2');

    Http::assertSent(fn ($request): bool => $request['before'] === 'cur-2');
});

it('walks the contact pool with `since`', function (): void {
    page(['items' => []]);

    Linqelio::contacts()->list(cursor: 'cur-2', limit: 25);

    Http::assertSent(fn ($request): bool => $request['since'] === 'cur-2'
        && ! isset($request['cursor']));
});

it('sends no cursor at all on the first page', function (): void {
    page(['contactId' => 'c-1', 'messages' => []]);

    Linqelio::messages()->history('c-1');

    Http::assertSent(fn ($request): bool => ! isset($request['before']));
});
