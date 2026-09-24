<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Enums\ParticipantRole;
use Linqelio\Laravel\Data\Enums\PolicyVerdict;
use Linqelio\Laravel\Exceptions\ConversationException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Sending into a conversation — the only way into a group, which is a
 * conversation and not a contact.
 */
it('sends into a conversation with the caller\'s idempotency key', function (): void {
    Http::fake(['*' => Http::response(['id' => '01A', 'type' => 'text', 'status' => 'queued'], 202)]);

    $message = Linqelio::conversations()->send('cv-1', MessageType::Text, ['text' => 'hi all'], idempotencyKey: 'grp-1');

    expect($message->id)->toBe('01A');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/conversations/cv-1/messages')
        && $r->header('Idempotency-Key')[0] === 'grp-1'
        && $r->data() === ['type' => 'text', 'content' => ['text' => 'hi all']]);
});

it('checks a conversation send', function (): void {
    Http::fake(['*' => Http::response([
        'verdict' => 'defer',
        'sendable' => false,
        'warningKeys' => [],
        'findings' => [['check' => 'hours', 'verdict' => 'defer', 'key' => 'hours', 'until' => '2026-09-25T09:00:00Z']],
        'meters' => [],
        'channelId' => 'ch-1',
        'cost' => 1,
        'retryAt' => '2026-09-25T09:00:00Z',
        'retryAfterSeconds' => 3600,
        'evaluatedAt' => '2026-09-25T08:00:00Z',
    ])]);

    $check = Linqelio::conversations()->check('cv-1', MessageType::Text, ['text' => 'hi']);

    expect($check->verdict)->toBe(PolicyVerdict::Defer)
        ->and($check->isDeferred())->toBeTrue()
        ->and($check->retryAfterSeconds)->toBe(3600)
        ->and($check->blockers())->toHaveCount(1)
        ->and($check->blockers()[0]->until?->format('H:i'))->toBe('09:00');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/conversations/cv-1/messages/policy-check')
        && $r->data() === ['type' => 'text', 'content' => ['text' => 'hi']]);
});

it('lists a group\'s participants', function (): void {
    Http::fake(['*/conversations/cv-1/participants' => Http::response([
        'conversationId' => 'cv-1',
        'items' => [
            ['providerId' => '380500000000', 'role' => 'admin', 'joinedAt' => '2026-09-01T00:00:00Z', 'contactId' => 'c-1'],
            ['providerId' => '380500000001', 'role' => 'member', 'joinedAt' => '2026-09-01T00:00:00Z', 'leftAt' => '2026-09-10T00:00:00Z'],
        ],
    ])]);

    $participants = Linqelio::conversations()->participants('cv-1');

    expect($participants)->toHaveCount(2)
        ->and($participants[0]->role)->toBe(ParticipantRole::Admin)
        ->and($participants[0]->contactId)->toBe('c-1')
        ->and($participants[1]->isPresent())->toBeFalse();
});

it('maps a direct conversation asked for participants to a ConversationException', function (): void {
    Http::fake(['*' => Http::response(['code' => 'conversation.not_group', 'detail' => 'not a group'], 409)]);

    expect(fn () => Linqelio::conversations()->participants('cv-2'))->toThrow(ConversationException::class);
});
