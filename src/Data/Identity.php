<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * One way a contact is reachable: a channel kind plus the address on it.
 *
 * `providerId` is the address the channel itself uses, and it is what the
 * platform links on. It is not always what you addressed: send to a Telegram
 * account by phone and the platform will re-key the identity to the numeric user
 * id once the messenger resolves it, keeping the phone as an attribute. The
 * fields below are read-only projections of the channel — the platform refreshes
 * them from inbound traffic.
 */
final readonly class Identity
{
    public function __construct(
        public string $channelType,
        public string $providerId,
        public ?string $phone = null,
        public ?string $username = null,
        public ?string $pushName = null,
        public ?string $avatarUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channelType: (string) ($data['channelType'] ?? ''),
            providerId: (string) ($data['providerId'] ?? ''),
            phone: self::stringOrNull($data['phone'] ?? null),
            username: self::stringOrNull($data['username'] ?? null),
            pushName: self::stringOrNull($data['pushName'] ?? null),
            avatarUrl: self::stringOrNull($data['avatarUrl'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
