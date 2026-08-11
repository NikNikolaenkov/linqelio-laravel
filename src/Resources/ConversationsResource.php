<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Message;

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
}
