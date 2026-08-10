<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\MessageStatus;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Exceptions\ChannelException;
use Linqelio\Laravel\Exceptions\ContactException;
use Linqelio\Laravel\Exceptions\IdempotencyException;
use Linqelio\Laravel\Exceptions\PolicyException;
use Linqelio\Laravel\Exceptions\ValidationException;
use Linqelio\Laravel\Facades\Linqelio;

it('sends a text message and reads back the queued result', function (): void {
    Http::fake([
        '*/contacts/c-1/messages' => Http::response([
            'id' => '01ABC',
            'direction' => 'outbound',
            'type' => 'text',
            'content' => ['text' => 'hello'],
            'status' => 'queued',
            'timestamps' => ['created' => '2026-01-01T10:00:00Z'],
        ], 202),
    ]);

    $message = Linqelio::messages()->sendText('c-1', 'hello');

    expect($message->id)->toBe('01ABC')
        ->and($message->type)->toBe(MessageType::Text)
        ->and($message->status)->toBe(MessageStatus::Queued)
        ->and($message->text())->toBe('hello');
});

it('always sends an Idempotency-Key, because a retried send must not duplicate', function (): void {
    Http::fake(['*' => Http::response(['id' => '01ABC', 'type' => 'text'], 202)]);

    Linqelio::messages()->sendText('c-1', 'hello');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Idempotency-Key')
        && $request->header('Idempotency-Key')[0] !== '');
});

it('passes through a caller-supplied idempotency key', function (): void {
    Http::fake(['*' => Http::response(['id' => '01ABC', 'type' => 'text'], 202)]);

    Linqelio::messages()->sendText('c-1', 'shipped', idempotencyKey: 'order-4711-shipped');

    Http::assertSent(fn ($request): bool => $request->header('Idempotency-Key')[0] === 'order-4711-shipped');
});

it('raises a typed exception per error domain', function (string $code, string $class): void {
    Http::fake(['*' => Http::response(['code' => $code, 'detail' => 'nope'], 422)]);

    expect(fn () => Linqelio::messages()->sendText('c-1', 'hi'))->toThrow($class);
})->with([
    ['contact.not_found', ContactException::class],
    ['idempotency.key_reused', IdempotencyException::class],
    ['policy.rate_limited', PolicyException::class],
    ['validation.invalid_request', ValidationException::class],
]);

it('keeps an unknown code usable instead of failing inside the error handler', function (): void {
    Http::fake(['*' => Http::response(['code' => 'channel.invented_tomorrow', 'detail' => 'x'], 400)]);

    // Additive registry: a code from a newer server should still land in the
    // right family rather than degrading to the base exception.
    expect(fn () => Linqelio::messages()->sendText('c-1', 'hi'))
        ->toThrow(ChannelException::class);
});

it('surfaces field errors from a validation problem', function (): void {
    Http::fake(['*' => Http::response([
        'code' => 'validation.invalid_request',
        'detail' => 'invalid',
        'errors' => [['field' => 'content', 'message' => 'required']],
    ], 400)]);

    try {
        Linqelio::messages()->sendText('c-1', '');
        $this->fail('expected a ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['content' => 'required']);
    }
});
