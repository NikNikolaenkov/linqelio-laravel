<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\MediaContent;
use Linqelio\Laravel\Data\Message;
use Linqelio\Laravel\Data\Policy\SendPolicyCheck;
use Linqelio\Laravel\Data\Policy\SendTemplate;
use Linqelio\Laravel\Exceptions\PolicyException;

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
     * Pass `$acknowledgedWarnings` to make it a CONFIRMED send: the
     * `warningKeys` of a {@see self::check()} somebody has seen and agreed to.
     * It then goes through only if every warning send policy raises NOW is
     * among them; a warning that appeared since is refused with
     * `policy.confirmation_required` ({@see PolicyException::needsConfirmation()})
     * and nothing is sent. An empty array confirms "no warnings". Leave it null
     * for an unconfirmed send, where warnings never block and only come back on
     * the result's `policyWarnings`.
     *
     * @param  array<string, mixed>  $content  keyed by type: ['text' => '…'] or ['media' => [...]]
     * @param  array<int, string>|null  $acknowledgedWarnings  the check's `warningKeys`, once confirmed
     * @param  SendTemplate|null  $template  for a `template` send (WhatsApp Business only)
     */
    public function send(
        string $contactId,
        MessageType $type,
        array $content,
        ?string $channelId = null,
        ?string $replyTo = null,
        ?string $idempotencyKey = null,
        ?array $acknowledgedWarnings = null,
        ?SendTemplate $template = null,
    ): Message {
        $body = SendBody::build($type, $content, $channelId, $replyTo, $acknowledgedWarnings, $template);

        $response = $this->client->post("/contacts/{$contactId}/messages", $body, idempotencyKey: $idempotencyKey);

        return Message::fromArray($response->data);
    }

    /**
     * Ask send policy about a message WITHOUT sending it.
     *
     * A dry run that consumes nothing — no rate budget, no counters — so call it
     * while somebody is composing. Same arguments as {@see self::send()}, so the
     * check is about exactly the message the send will carry:
     *
     *     $check = Linqelio::messages()->check($id, MessageType::Text, $content);
     *     // show $check->findings; if $check->needsConfirmation(), ask
     *     Linqelio::messages()->send($id, MessageType::Text, $content,
     *         acknowledgedWarnings: $check->warningKeys);
     *
     * @param  array<string, mixed>  $content
     */
    public function check(
        string $contactId,
        MessageType $type,
        array $content,
        ?string $channelId = null,
        ?string $replyTo = null,
        ?SendTemplate $template = null,
    ): SendPolicyCheck {
        $body = SendBody::build($type, $content, $channelId, $replyTo, template: $template);

        return SendPolicyCheck::fromArray(
            $this->client->post("/contacts/{$contactId}/messages/policy-check", $body)->data,
        );
    }

    /**
     * Send an approved WhatsApp Business template — the one kind of message a
     * wa_cloud channel may send outside the 24-hour service window.
     *
     * @param  array<int, string>|null  $acknowledgedWarnings
     */
    public function sendTemplate(
        string $contactId,
        SendTemplate $template,
        ?string $channelId = null,
        ?string $idempotencyKey = null,
        ?array $acknowledgedWarnings = null,
    ): Message {
        return $this->send(
            $contactId,
            MessageType::Template,
            [],
            $channelId,
            idempotencyKey: $idempotencyKey,
            acknowledgedWarnings: $acknowledgedWarnings,
            template: $template,
        );
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
