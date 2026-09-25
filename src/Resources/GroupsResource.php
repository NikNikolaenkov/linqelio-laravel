<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Groups\GroupChange;
use Linqelio\Laravel\Data\Groups\IgnoredGroups;

/**
 * Group chats a channel's account takes part in (paired WhatsApp and Telegram
 * accounts).
 *
 * Every change happens on the messenger, as the channel's account, so it can be
 * partial: check {@see GroupChange::$failed}. Members are contacts of the
 * cabinet — a person has to be a contact before you can add them. Read the
 * current participants with `conversations()->participants()`, and send into a
 * group with `conversations()->send()`.
 *
 * Groups can be switched off per channel (`policy.groups_disabled`).
 *
 * Every change but the rename takes an optional `$idempotencyKey`: pinned, a
 * retry with the same key replays the first answer instead of acting on the
 * messenger twice (ADR-0103).
 */
final readonly class GroupsResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Create a group on a channel with contacts of the cabinet as its members.
     *
     * @param  array<int, string>  $contactIds
     */
    public function create(string $channelId, string $subject, array $contactIds, ?string $idempotencyKey = null): GroupChange
    {
        $response = $this->client->post("/channels/{$channelId}/groups", [
            'subject' => $subject,
            'contactIds' => array_values($contactIds),
        ], idempotencyKey: $idempotencyKey);

        return GroupChange::fromArray($response->data);
    }

    /**
     * @param  array<int, string>  $contactIds
     */
    public function addParticipants(string $conversationId, array $contactIds, ?string $idempotencyKey = null): GroupChange
    {
        $response = $this->client->post("/conversations/{$conversationId}/participants/add", [
            'contactIds' => array_values($contactIds),
        ], idempotencyKey: $idempotencyKey);

        return GroupChange::fromArray($response->data);
    }

    /**
     * Remove participants — by their messenger `providerId`, because somebody in
     * a group need not be a contact.
     *
     * @param  array<int, string>  $providerIds
     */
    public function removeParticipants(string $conversationId, array $providerIds, ?string $idempotencyKey = null): GroupChange
    {
        $response = $this->client->post("/conversations/{$conversationId}/participants/remove", [
            'providerIds' => array_values($providerIds),
        ], idempotencyKey: $idempotencyKey);

        return GroupChange::fromArray($response->data);
    }

    public function rename(string $conversationId, string $subject): GroupChange
    {
        return GroupChange::fromArray(
            $this->client->patch("/conversations/{$conversationId}/group", ['subject' => $subject])->data,
        );
    }

    /**
     * Make the channel's account leave the group. The conversation and its
     * history stay; nothing new arrives in it.
     */
    public function leave(string $conversationId, ?string $idempotencyKey = null): void
    {
        $this->client->post("/conversations/{$conversationId}/group/leave", idempotencyKey: $idempotencyKey);
    }

    /**
     * The groups a number is in while its group chats are switched off — what an
     * operator would start receiving by turning them on.
     */
    public function ignored(string $channelId): IgnoredGroups
    {
        return IgnoredGroups::fromArray($this->client->get("/channels/{$channelId}/ignored-groups")->data);
    }
}
