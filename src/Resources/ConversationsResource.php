<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Groups\Participant;
use Linqelio\Laravel\Data\Message;
use Linqelio\Laravel\Data\Policy\SendPolicyCheck;
use Linqelio\Laravel\Data\Policy\SendTemplate;

/**
 * Threads as an operator sees them: one per person per channel.
 *
 * Distinct from a contact's history, which spans channels. A person who writes
 * on both WhatsApp and Telegram is one contact with two conversations.
 */
final readonly class ConversationsResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * @return array{conversations: array<int, array<string, mixed>>, nextCursor: ?string}
     */
    public function list(
        ?string $channelId = null,
        ?string $status = null,
        ?string $since = null,
        ?int $limit = null,
    ): array {
        $response = $this->client->get('/conversations', array_filter([
            'channelId' => $channelId,
            'status' => $status,
            'since' => $since,
            'limit' => $limit,
        ], static fn ($v): bool => $v !== null));

        return [
            'conversations' => $response->items(),
            'nextCursor' => $response->nextCursor(),
        ];
    }

    /**
     * One thread, oldest to newest. `before` walks further back.
     *
     * @return array{messages: array<int, Message>, nextCursor: ?string}
     */
    public function feed(string $conversationId, ?string $before = null, ?int $limit = null): array
    {
        $response = $this->client->get("/conversations/{$conversationId}/feed", array_filter([
            'before' => $before,
            'limit' => $limit,
        ], static fn ($v): bool => $v !== null));

        return [
            'messages' => array_map(Message::fromArray(...), $response->collection('messages')),
            'nextCursor' => $response->nextCursor(),
        ];
    }

    /**
     * Send into a conversation — a direct chat, or a GROUP, which is the one
     * thing a contact send cannot reach: a group is a conversation, not a
     * contact.
     *
     * The conversation fixes the channel; `$channelId` may only repeat it.
     * Confirmed sends work as for a contact — see `messages()->send()`.
     *
     * @param  array<string, mixed>  $content
     * @param  array<int, string>|null  $acknowledgedWarnings  the check's `warningKeys`, once confirmed
     */
    public function send(
        string $conversationId,
        MessageType $type,
        array $content,
        ?string $channelId = null,
        ?string $replyTo = null,
        ?string $idempotencyKey = null,
        ?array $acknowledgedWarnings = null,
        ?SendTemplate $template = null,
    ): Message {
        $body = SendBody::build($type, $content, $channelId, $replyTo, $acknowledgedWarnings, $template);

        $response = $this->client->post("/conversations/{$conversationId}/messages", $body, idempotencyKey: $idempotencyKey);

        return Message::fromArray($response->data);
    }

    /**
     * Ask send policy about a message into this conversation, without sending.
     *
     * @param  array<string, mixed>  $content
     */
    public function check(
        string $conversationId,
        MessageType $type,
        array $content,
        ?string $channelId = null,
        ?string $replyTo = null,
        ?SendTemplate $template = null,
    ): SendPolicyCheck {
        $body = SendBody::build($type, $content, $channelId, $replyTo, template: $template);

        return SendPolicyCheck::fromArray(
            $this->client->post("/conversations/{$conversationId}/messages/policy-check", $body)->data,
        );
    }

    /**
     * A group conversation's participants, including those who have left.
     * Asking a direct conversation answers `conversation.not_group`.
     *
     * @return array<int, Participant>
     */
    public function participants(string $conversationId): array
    {
        return array_map(
            Participant::fromArray(...),
            $this->client->get("/conversations/{$conversationId}/participants")->items(),
        );
    }
}
