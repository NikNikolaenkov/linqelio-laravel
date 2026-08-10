<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * An outbound webhook subscription: one endpoint of yours, and the events sent
 * to it.
 *
 * The signing secret is never part of this — it is write-only on registration
 * and no read returns it. If you lose it, you register a new subscription.
 */
final readonly class Webhook
{
    /**
     * @param  array<int, string>  $eventTypes  empty means every event type
     */
    public function __construct(
        public string $id,
        public string $url,
        public string $status,
        public array $eventTypes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, string> $events */
        $events = is_array($data['eventTypes'] ?? null)
            ? array_values(array_map(strval(...), $data['eventTypes']))
            : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            status: (string) ($data['status'] ?? 'active'),
            eventTypes: $events,
        );
    }

    /** Delivering. A disabled subscription still exists and still holds its key. */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
