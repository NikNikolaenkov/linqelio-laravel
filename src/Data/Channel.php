<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

use Linqelio\Laravel\Data\Enums\ChannelKind;

/**
 * A configured channel inside a cabinet — one bot, one paired phone, one account.
 */
final readonly class Channel
{
    public function __construct(
        public string $id,
        public ChannelKind $kind,
        public string $state,
        public ?string $name = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            kind: ChannelKind::tryFrom((string) ($data['kind'] ?? '')) ?? ChannelKind::WaWeb,
            state: (string) ($data['state'] ?? $data['status'] ?? 'inactive'),
            name: isset($data['name']) && $data['name'] !== '' ? (string) $data['name'] : null,
        );
    }

    /** Ready to carry messages. Anything else needs attention before sending. */
    public function isActive(): bool
    {
        return $this->state === 'active' || $this->state === 'connected';
    }

    /** Waiting for a human to scan a code. */
    public function isPairing(): bool
    {
        return $this->state === 'pairing';
    }
}
