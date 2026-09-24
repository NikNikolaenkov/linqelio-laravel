<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use DateTimeInterface;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Enums\ScheduledSendStatus;
use Linqelio\Laravel\Data\Read;
use Linqelio\Laravel\Data\Scheduling\ScheduledSend;

/**
 * One message to one contact, held by the platform until a later moment.
 *
 * Prefer this over a delayed job on your side when the send must survive your
 * own deploys and queue outages, and when an operator should be able to see and
 * cancel it in the console.
 */
final readonly class ScheduledSendsResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Pin `$idempotencyKey` when the schedule is worth retrying: a replay of
     * the same key answers with the ORIGINAL scheduled send instead of queuing
     * a second message.
     *
     * @param  array<string, mixed>  $content  keyed by type, as for a direct send
     */
    public function create(
        string $contactId,
        DateTimeInterface $sendAt,
        MessageType $type,
        array $content,
        ?string $channelId = null,
        ?string $replyTo = null,
        ?string $idempotencyKey = null,
    ): ScheduledSend {
        $body = Read::compact([
            'contactId' => $contactId,
            'channelId' => $channelId,
            'sendAt' => Read::timestamp($sendAt),
            'type' => $type->value,
            'content' => $content === [] ? new \stdClass : $content,
            'replyTo' => $replyTo,
        ]);

        return ScheduledSend::fromArray($this->client->post('/scheduled-sends', $body, idempotencyKey: $idempotencyKey)->data);
    }

    /**
     * Soonest first.
     *
     * @return array{scheduledSends: array<int, ScheduledSend>, nextCursor: ?string}
     */
    public function list(
        ?string $contactId = null,
        ?ScheduledSendStatus $status = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        $response = $this->client->get('/scheduled-sends', Read::compact([
            'contactId' => $contactId,
            'status' => $status?->value,
            'since' => $cursor,
            'limit' => $limit,
        ]));

        return [
            'scheduledSends' => array_map(ScheduledSend::fromArray(...), $response->items()),
            'nextCursor' => $response->nextCursor(),
        ];
    }

    public function find(string $scheduledSendId): ScheduledSend
    {
        return ScheduledSend::fromArray($this->client->get("/scheduled-sends/{$scheduledSendId}")->data);
    }

    /**
     * Move it, or change its message — only while it is still `scheduled`;
     * afterwards the answer is `scheduled_send.state_conflict`. A null argument
     * leaves that part as it is.
     *
     * @param  array<string, mixed>|null  $content
     */
    public function update(
        string $scheduledSendId,
        ?DateTimeInterface $sendAt = null,
        ?MessageType $type = null,
        ?array $content = null,
    ): ScheduledSend {
        $response = $this->client->patch("/scheduled-sends/{$scheduledSendId}", Read::compact([
            'sendAt' => Read::timestamp($sendAt),
            'type' => $type?->value,
            'content' => $content,
        ]));

        return ScheduledSend::fromArray($response->data);
    }

    /** Cancel it before it goes. */
    public function cancel(string $scheduledSendId): ScheduledSend
    {
        return ScheduledSend::fromArray($this->client->post("/scheduled-sends/{$scheduledSendId}/cancel")->data);
    }
}
