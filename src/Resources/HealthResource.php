<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use DateTimeInterface;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Health\ChannelHealth;
use Linqelio\Laravel\Data\Health\HealthPoint;
use Linqelio\Laravel\Data\Read;

/**
 * Channel health: a score per channel, explained, and its history.
 *
 * A number that sends too much, too fast, or gets reported, is banned by the
 * messenger with no appeal. The score is the early warning; alerts
 * (`Linqelio::alerts()`) are how it reaches somebody.
 */
final readonly class HealthResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Every channel the key may see.
     *
     * @return array<int, ChannelHealth>
     */
    public function list(): array
    {
        return array_map(ChannelHealth::fromArray(...), $this->client->get('/channel-health')->items());
    }

    public function find(string $channelId): ChannelHealth
    {
        return ChannelHealth::fromArray($this->client->get("/channel-health/{$channelId}")->data);
    }

    /**
     * A channel's score over time, from `$since` on.
     *
     * @return array<int, HealthPoint>
     */
    public function history(string $channelId, ?DateTimeInterface $since = null, ?int $limit = null): array
    {
        $response = $this->client->get("/channel-health/{$channelId}/history", Read::compact([
            'since' => Read::timestamp($since),
            'limit' => $limit,
        ]));

        return array_map(HealthPoint::fromArray(...), $response->items());
    }
}
