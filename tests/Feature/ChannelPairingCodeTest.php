<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Exceptions\LinqelioException;
use Linqelio\Laravel\Facades\Linqelio;

it('asks for the code that links a WhatsApp Web account by phone number', function (): void {
    Http::fake(['*/channels/ch-1/pairing-code' => Http::response([
        'channelId' => 'ch-1', 'state' => 'pairing', 'pairingCode' => 'K7QX-M3PZ',
    ])]);

    $status = Linqelio::channels()->pairingCode('ch-1', '+380501234567');

    expect($status['pairingCode'])->toBe('K7QX-M3PZ')
        ->and($status['state'])->toBe('pairing');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/channels/ch-1/pairing-code')
        && $r->data() === ['phone' => '+380501234567']);
});

it('raises the platform\'s refusal when a code was just issued', function (): void {
    Http::fake(['*' => Http::response(
        ['code' => 'policy.rate_limited', 'detail' => 'a pairing code was just issued for this channel'],
        429,
        ['Retry-After' => '15'],
    )]);

    expect(fn () => Linqelio::channels()->pairingCode('ch-1', '+380501234567'))
        ->toThrow(LinqelioException::class);
});
