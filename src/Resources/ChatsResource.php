<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Chats\ChannelChats;
use Linqelio\Laravel\Data\Chats\ChatMember;
use Linqelio\Laravel\Data\Chats\ChatMembersResult;
use Linqelio\Laravel\Data\Chats\ChatRemoval;

/**
 * Members of the Telegram groups, supergroups and channels a Telegram account
 * channel (`tg_client`) administers, by phone number (ADR-0134).
 *
 * Unlike `groups()`, a person here need not be a contact of the cabinet: the
 * chat is addressed by its Telegram address (`chat:<id>` or `channel:<id>`, as
 * {@see self::list()} shows it) and a person by phone. Every change acts on real
 * people as the channel's own account, which must be an admin of the chat.
 *
 * Answers are per person or per chat, and one refusal does not fail the call:
 * read the results rather than trusting a 200. Membership changes, including
 * ones made in Telegram by others, arrive as the `chat.member_added`,
 * `chat.member_removed`, `chat.member_joined` and `chat.member_left` webhooks
 * (through the `WebhookReceived` event).
 */
final readonly class ChatsResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * The chats where the channel's account is the owner or an admin, with what
     * it may do there (`canInvite`, `canRemove`). Check
     * {@see ChannelChats::$complete}: a very long dialog list is cut off.
     */
    public function list(string $channelId): ChannelChats
    {
        return ChannelChats::fromArray($this->client->get("/channels/{$channelId}/chats")->data);
    }

    /**
     * Add people (1–50) to a chat. Someone Telegram refuses to add because of
     * their privacy settings is sent a personal invite link in a message
     * instead (`invite_queued`), so the key also needs `messages:send`.
     *
     * Pin `$idempotencyKey` to make a retry safe (ADR-0103).
     *
     * @param  array<int, ChatMember|string>  $members  a ChatMember, or just the phone number
     */
    public function addMembers(string $channelId, string $chatId, array $members, ?string $idempotencyKey = null): ChatMembersResult
    {
        $response = $this->client->post("/channels/{$channelId}/chats/{$chatId}/members/add", [
            'members' => self::members($members),
        ], idempotencyKey: $idempotencyKey);

        return ChatMembersResult::fromArray($response->data);
    }

    /**
     * Remove people (1–50) from a chat. Removal is a kick: they may rejoin. The
     * chat's owner and admins are not removed (`failed`, reason `admin`).
     *
     * @param  array<int, ChatMember|string>  $members  a ChatMember, or just the phone number
     */
    public function removeMembers(string $channelId, string $chatId, array $members, ?string $idempotencyKey = null): ChatMembersResult
    {
        $response = $this->client->post("/channels/{$channelId}/chats/{$chatId}/members/remove", [
            'members' => self::members($members),
        ], idempotencyKey: $idempotencyKey);

        return ChatMembersResult::fromArray($response->data);
    }

    /**
     * Remove one person from every chat the account administers, or only from
     * `$chatIds` (at most 200) — say, when someone leaves the company.
     *
     * Repeat until {@see ChatRemoval::$complete} is true, waiting
     * `retryAfterSeconds` when it is set. It takes no Idempotency-Key: repeating
     * it is how it is finished.
     *
     * @param  array<int, string>|null  $chatIds
     */
    public function removeEverywhere(string $channelId, string $phone, ?array $chatIds = null): ChatRemoval
    {
        $body = ['phone' => $phone];

        if ($chatIds !== null) {
            $body['chatIds'] = array_values($chatIds);
        }

        return ChatRemoval::fromArray($this->client->post("/channels/{$channelId}/chat-members/remove", $body)->data);
    }

    /**
     * @param  array<int, ChatMember|string>  $members
     * @return array<int, array<string, string>>
     */
    private static function members(array $members): array
    {
        return array_values(array_map(
            static fn (ChatMember|string $m): array => (is_string($m) ? new ChatMember($m) : $m)->toArray(),
            $members,
        ));
    }
}
