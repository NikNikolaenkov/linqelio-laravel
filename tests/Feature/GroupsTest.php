<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\GroupMemberFailureReason;
use Linqelio\Laravel\Exceptions\ConversationException;
use Linqelio\Laravel\Exceptions\PolicyException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Group changes happen on the messenger and can be partial — the result names
 * who did not make it, and a caller that ignores it thinks everyone is in.
 */
function groupChangeBody(): array
{
    return [
        'conversationId' => 'cv-9',
        'chatId' => '1203@g.us',
        'subject' => 'Team',
        'requested' => [['contactId' => 'c-1'], ['contactId' => 'c-2']],
        'failed' => [['contactId' => 'c-2', 'reason' => 'no_address']],
        'membersRefreshed' => true,
    ];
}

it('creates a group and reports who could not be added', function (): void {
    Http::fake(['*' => Http::response(groupChangeBody(), 201)]);

    $change = Linqelio::groups()->create('ch-1', 'Team', ['c-1', 'c-2']);

    expect($change->conversationId)->toBe('cv-9')
        ->and($change->isComplete())->toBeFalse()
        ->and($change->failed[0]->contactId)->toBe('c-2')
        ->and($change->failed[0]->reason)->toBe(GroupMemberFailureReason::NoAddress)
        ->and($change->requested)->toHaveCount(2);

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/channels/ch-1/groups')
        && $r->data() === ['subject' => 'Team', 'contactIds' => ['c-1', 'c-2']]);
});

it('shapes each group change as the contract names it', function (Closure $call, string $method, string $path, array $body): void {
    Http::fake(['*' => Http::response(groupChangeBody())]);

    $call();

    Http::assertSent(fn (Request $r): bool => $r->method() === $method
        && str_ends_with($r->url(), $path)
        && $r->data() === $body);
})->with([
    'add' => [fn () => Linqelio::groups()->addParticipants('cv-9', ['c-3']), 'POST', '/conversations/cv-9/participants/add', ['contactIds' => ['c-3']]],
    'remove' => [fn () => Linqelio::groups()->removeParticipants('cv-9', ['380500']), 'POST', '/conversations/cv-9/participants/remove', ['providerIds' => ['380500']]],
    'rename' => [fn () => Linqelio::groups()->rename('cv-9', 'Renamed'), 'PATCH', '/conversations/cv-9/group', ['subject' => 'Renamed']],
    'leave' => [fn () => Linqelio::groups()->leave('cv-9'), 'POST', '/conversations/cv-9/group/leave', []],
]);

it('lists the groups a number ignores', function (): void {
    Http::fake(['*/channels/ch-1/ignored-groups' => Http::response([
        'channelId' => 'ch-1',
        'groupsEnabled' => false,
        'items' => [['chatId' => '1@g.us', 'subject' => 'Family', 'firstSeenAt' => '2026-09-01T00:00:00Z', 'lastActivityAt' => '2026-09-20T00:00:00Z', 'messages24h' => 40]],
    ])]);

    $ignored = Linqelio::groups()->ignored('ch-1');

    expect($ignored->groupsEnabled)->toBeFalse()
        ->and($ignored->groups[0]['subject'])->toBe('Family')
        ->and($ignored->groups[0]['messages24h'])->toBe(40);
});

it('maps group refusals to their families', function (string $code, string $class): void {
    Http::fake(['*' => Http::response(['code' => $code, 'detail' => 'no'], 409)]);

    expect(fn () => Linqelio::groups()->rename('cv-9', 'x'))->toThrow($class);
})->with([
    ['conversation.not_group', ConversationException::class],
    ['group.members_unresolvable', ConversationException::class],
    ['policy.groups_disabled', PolicyException::class],
]);
