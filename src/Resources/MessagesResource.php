<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\MediaContent;
use Linqelio\Laravel\Data\Message;

final readonly class MessagesResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Send a message to a contact.
     *
     * Returns the queued message: the API accepts the command and reaches the
     * provider afterwards, so a successful call means "handed over", not
     * "delivered". Delivery shows up later, on a webhook or a re-read.
     *
     * The channel is chosen for you — the contact's most recent conversation,
     * falling back to a channel matching one of their identities. Pin it with
     * `$channelId` when it matters, but note the contact must be addressable
     * there or the send fails with `channel.capability_unsupported` rather than
     * quietly going out somewhere else.
     *
     * @param  array<string, mixed>  $content  keyed by type: ['text' => '…'] or ['media' => [...]]
     */
    public function send(
        string $contactId,
        MessageType $type,
        array $content,
        ?string $channelId = null,
        ?string $replyTo = null,
        ?string $idempotencyKey = null,
    ): Message {
        $body = array_filter([
            'type' => $type->value,
            'content' => $content,
            'channelId' => $channelId,
            'replyTo' => $replyTo,
        ], static fn ($v): bool => $v !== null);

        $response = $this->client->post("/contacts/{$contactId}/messages", $body, idempotencyKey: $idempotencyKey);

        return Message::fromArray($response->data);
    }

    /** Convenience for the common case. */
    public function sendText(
        string $contactId,
        string $text,
        ?string $channelId = null,
        ?string $idempotencyKey = null,
    ): Message {
        return $this->send($contactId, MessageType::Text, ['text' => $text], $channelId, idempotencyKey: $idempotencyKey);
    }

    /**
     * Send an attachment that has already been uploaded.
     *
     * Upload first with `Linqelio::media()->upload(...)`; providers fetch the
     * file by URL, so the bytes have to be somewhere reachable before the send.
     */
    public function sendMedia(
        string $contactId,
        MessageType $type,
        MediaContent $media,
        ?string $caption = null,
        ?string $channelId = null,
        ?string $idempotencyKey = null,
    ): Message {
        return $this->send($contactId, $type, $media->toContent($caption), $channelId, idempotencyKey: $idempotencyKey);
    }

    /**
     * Read one message and its current status.
     *
     * A send is accepted, not delivered — `send()` returns as soon as the
     * platform takes the command, and the provider is reached afterwards. This is
     * how the outcome is checked: queued, sent, delivered, read or failed, with
     * the timestamp of each step it reached.
     *
     * For a failed send, `$message->failureReason()` says why.
     *
     * At any volume prefer the `message.status` webhook — one call per message
     * does not scale, and polling for an outcome that may take seconds is how a
     * queue worker ends up sleeping. This answers for ONE message, which is what
     * a support desk needs when somebody asks about a specific reply.
     */
    public function find(string $id): Message
    {
        return Message::fromArray($this->client->get("/messages/{$id}")->data);
    }

    /**
     * A contact's history across every channel, newest first.
     *
     * @return array{messages: array<int, Message>, nextCursor: ?string}
     */
    public function history(string $contactId, ?string $cursor = null, ?int $limit = null): array
    {
        $response = $this->client->get("/contacts/{$contactId}/messages", array_filter([
            // `before` on the wire: this endpoint walks backwards, and the
            // contract spells that cursor differently from the forward `since`
            // the pool endpoints take. Both are the opaque `pageInfo.nextCursor`
            // from the previous page, which is why the argument stays $cursor.
            'before' => $cursor,
            'limit' => $limit,
        ], static fn ($v): bool => $v !== null));

        return [
            'messages' => array_map(Message::fromArray(...), $response->collection('messages')),
            'nextCursor' => $response->nextCursor(),
        ];
    }
}
