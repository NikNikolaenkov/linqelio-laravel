<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Facades\Linqelio;

// A WhatsApp Cloud channel needs three write-only values. Sending only the token
// leaves a channel that sends fine and rejects every inbound webhook, so the
// call has to carry all three in one request.
it('sends all three WhatsApp Cloud credentials together', function (): void {
    Http::fake(['*' => Http::response(null, 204)]);

    Linqelio::channels()->setCredentials('ch-1', 'graph-token', 'app-secret', 'verify-token');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->method() === 'PUT'
            && str_ends_with($request->url(), '/channels/ch-1/credentials')
            && $body['token'] === 'graph-token'
            && $body['appSecret'] === 'app-secret'
            && $body['verifyToken'] === 'verify-token';
    });
});

// The extras are refused by the platform on any other kind, so sending empty
// keys for a bot token would turn a valid call into a rejected one.
it('omits the Meta fields entirely for a plain token channel', function (): void {
    Http::fake(['*' => Http::response(null, 204)]);

    Linqelio::channels()->setCredentials('ch-1', 'bot-token');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $body === ['token' => 'bot-token'];
    });
});

it('stores the sending number as channel settings', function (): void {
    Http::fake(['*' => Http::response(['phoneNumberId' => '15551234567'], 200)]);

    $stored = Linqelio::channels()->settings('ch-1', '15551234567');

    expect($stored['phoneNumberId'])->toBe('15551234567');

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/channels/ch-1/settings')
        && $request->data() === ['phoneNumberId' => '15551234567']);
});

// PUT replaces: passing nothing clears the settings rather than leaving them
// alone. Sending an empty object is how that intent is expressed.
it('clears settings when given nothing', function (): void {
    Http::fake(['*' => Http::response([], 200)]);

    Linqelio::channels()->settings('ch-1');

    Http::assertSent(fn ($request): bool => $request->data() === []);
});

// Settings read back — that is the whole reason they are not credentials.
it('exposes the sending number on a listed channel', function (): void {
    Http::fake([
        '*/channels' => Http::response([
            'items' => [
                ['id' => 'ch-1', 'kind' => 'wa_cloud', 'state' => 'active', 'settings' => ['phoneNumberId' => '15551234567']],
                ['id' => 'ch-2', 'kind' => 'tg_bot', 'state' => 'active'],
            ],
        ], 200),
    ]);

    $channels = Linqelio::channels()->list();

    expect($channels[0]->phoneNumberId)->toBe('15551234567')
        // A channel with no settings must read as null, not as an empty string:
        // "not configured" and "configured to nothing" are different answers.
        ->and($channels[1]->phoneNumberId)->toBeNull();
});
