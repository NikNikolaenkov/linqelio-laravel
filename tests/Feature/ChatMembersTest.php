<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Chats\ChatMember;
use Linqelio\Laravel\Data\Enums\ChatMemberOutcome;
use Linqelio\Laravel\Data\Enums\ChatRemovalOutcome;
use Linqelio\Laravel\Data\Enums\ChatRole;
use Linqelio\Laravel\Data\Enums\ChatType;
use Linqelio\Laravel\Data\Enums\ErrorCode;
use Linqelio\Laravel\Exceptions\ConversationException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Telegram chat members by phone (ADR-0134). Answers are per person or per chat
 * and one refusal does not fail the call, so the tests read the rows, not the
 * status.
 */
it('lists the chats the account administers', function (): void {
    Http::fake(['*/channels/ch-1/chats' => Http::response([
        'channelId' => 'ch-1',
        'complete' => false,
        'items' => [[
            'chatId' => 'chat:1234567890',
            'type' => 'group',
            'title' => 'Kovalenko family order',
            'username' => null,
            'membersCount' => 12,
            'role' => 'owner',
            'canInvite' => true,
            'canRemove' => false,
            'conversationId' => 'cv-1',
        ]],
    ])]);

    $chats = Linqelio::chats()->list('ch-1');

    expect($chats->complete)->toBeFalse()
        ->and($chats->chats)->toHaveCount(1)
        ->and($chats->chats[0]->chatId)->toBe('chat:1234567890')
        ->and($chats->chats[0]->type)->toBe(ChatType::Group)
        ->and($chats->chats[0]->role)->toBe(ChatRole::Owner)
        ->and($chats->chats[0]->membersCount)->toBe(12)
        ->and($chats->chats[0]->canInvite)->toBeTrue()
        ->and($chats->chats[0]->canRemove)->toBeFalse()
        ->and($chats->chats[0]->conversationId)->toBe('cv-1');
});

it('adds people by phone and reports each one', function (): void {
    Http::fake(['*' => Http::response([
        'chatId' => 'chat:1234567890',
        'title' => 'Kovalenko family order',
        'results' => [
            ['phone' => '+380501234567', 'outcome' => 'added', 'contactId' => 'c-1', 'providerId' => '777'],
            ['phone' => '+380671112233', 'outcome' => 'invite_not_sent', 'reason' => 'premium_required', 'inviteLink' => 'https://t.me/+AbC', 'inviteExpiresAt' => '2026-11-30T10:00:00Z'],
        ],
    ])]);

    $result = Linqelio::chats()->addMembers(
        'ch-1',
        'chat:1234567890',
        [new ChatMember('+380501234567', 'Olena'), '+380671112233'],
        idempotencyKey: 'k-1',
    );

    expect($result->results)->toHaveCount(2)
        ->and($result->results[0]->outcome)->toBe(ChatMemberOutcome::Added)
        ->and($result->results[0]->contactId)->toBe('c-1')
        ->and($result->failed())->toHaveCount(1)
        ->and($result->failed()[0]->reason)->toBe('premium_required')
        ->and($result->failed()[0]->inviteLink)->toBe('https://t.me/+AbC')
        ->and($result->failed()[0]->inviteExpiresAt?->format('Y-m-d'))->toBe('2026-11-30');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/channels/ch-1/chats/chat:1234567890/members/add')
        && $r->header('Idempotency-Key') === ['k-1']
        && $r->data() === ['members' => [
            ['phone' => '+380501234567', 'name' => 'Olena'],
            ['phone' => '+380671112233'],
        ]]);
});

it('removes people from one chat', function (): void {
    Http::fake(['*' => Http::response([
        'chatId' => 'channel:22',
        'results' => [['phone' => '+380501234567', 'outcome' => 'failed', 'reason' => 'admin']],
        'retryAfterSeconds' => 30,
    ])]);

    $result = Linqelio::chats()->removeMembers('ch-1', 'channel:22', ['+380501234567']);

    expect($result->results[0]->outcome)->toBe(ChatMemberOutcome::Failed)
        ->and($result->results[0]->reason)->toBe('admin')
        ->and($result->retryAfterSeconds)->toBe(30);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/channels/ch-1/chats/channel:22/members/remove')
        && $r->data() === ['members' => [['phone' => '+380501234567']]]);
});

it('removes one person from every chat until the answer is complete', function (): void {
    Http::fake(['*' => Http::response([
        'phone' => '+380501234567',
        'complete' => false,
        'contactId' => 'c-1',
        'providerId' => '777',
        'retryAfterSeconds' => 30,
        'results' => [
            ['chatId' => 'chat:1', 'title' => 'Team', 'outcome' => 'removed'],
            ['chatId' => 'channel:2', 'outcome' => 'not_processed'],
        ],
    ])]);

    $removal = Linqelio::chats()->removeEverywhere('ch-1', '+380501234567');

    expect($removal->complete)->toBeFalse()
        ->and($removal->retryAfterSeconds)->toBe(30)
        ->and($removal->results[0]->outcome)->toBe(ChatRemovalOutcome::Removed)
        ->and($removal->results[1]->outcome?->isPending())->toBeTrue();

    // Without chatIds the body leaves them out: every chat of the account.
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/channels/ch-1/chat-members/remove')
        && $r->data() === ['phone' => '+380501234567']);
});

it('maps chat refusals to the conversation family', function (string $code): void {
    Http::fake(['*' => Http::response(['code' => $code, 'detail' => 'no'], 403)]);

    expect(fn () => Linqelio::chats()->addMembers('ch-1', 'chat:1', ['+380501234567']))
        ->toThrow(ConversationException::class);
})->with([
    ErrorCode::ChatNotFound->value,
    ErrorCode::ChatAdminRequired->value,
]);
