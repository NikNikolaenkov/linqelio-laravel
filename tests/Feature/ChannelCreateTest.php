<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Exceptions\IdempotencyException;
use Linqelio\Laravel\Facades\Linqelio;

$created = ['id' => 'ch-1', 'kind' => 'tg_bot', 'state' => 'disconnected'];

// The transport puts a key on every unsafe command, so the header is always
// there. On its own it protects nothing here: each attempt generates a new one.
it('sends a generated Idempotency-Key when the caller pins none', function () use ($created): void {
    Http::fake(['*' => Http::response($created, 201)]);

    Linqelio::channels()->create(ChannelKind::TgBot);

    Http::assertSent(fn ($request): bool => $request->hasHeader('Idempotency-Key')
        && $request->header('Idempotency-Key')[0] !== '');
});

// This is why pinning matters, and it is the whole reason the parameter exists:
// two attempts at the SAME channel carry two different generated keys, so the
// platform sees two distinct creates and provisions two channels — neither of
// which can be deleted afterwards.
it('generates a different key per call, so an unpinned retry would not dedupe', function () use ($created): void {
    Http::fake(['*' => Http::response($created, 201)]);

    Linqelio::channels()->create(ChannelKind::TgBot, 'support');
    Linqelio::channels()->create(ChannelKind::TgBot, 'support');

    $keys = [];
    Http::assertSent(function ($request) use (&$keys): bool {
        $keys[] = $request->header('Idempotency-Key')[0];

        return true;
    });

    expect($keys)->toHaveCount(2)
        ->and($keys[0])->not->toBe($keys[1]);
});

it('passes through a caller-pinned idempotency key', function () use ($created): void {
    Http::fake(['*' => Http::response($created, 201)]);

    Linqelio::channels()->create(ChannelKind::TgBot, 'support', 'channel-42-tg-support');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/channels')
        && $request->header('Idempotency-Key')[0] === 'channel-42-tg-support'
        && $request->data() === ['kind' => 'tg_bot', 'label' => 'support']);
});

// A pinned key that already named a different channel is the platform refusing
// to hand back something the caller did not ask for.
it('raises IdempotencyException when the key already named another channel', function (): void {
    Http::fake(['*' => Http::response(['code' => 'idempotency.key_reused', 'detail' => 'nope'], 409)]);

    expect(fn () => Linqelio::channels()->create(ChannelKind::ViberBot, 'support', 'channel-42-tg-support'))
        ->toThrow(IdempotencyException::class);
});
