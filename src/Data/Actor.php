<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * Who did something: a person (`account`), an API key (`api_key`) or the
 * platform itself (`system`).
 *
 * Kept as the platform reports it rather than resolved into a name — the id is
 * the stable part, and the set of actor types grows.
 */
final readonly class Actor
{
    public function __construct(
        public string $type,
        public ?string $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Read::string($data, 'type'),
            id: Read::stringOrNull($data, 'id'),
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromNullable(?array $data): ?self
    {
        return $data === null ? null : self::fromArray($data);
    }

    public function isApiKey(): bool
    {
        return $this->type === 'api_key';
    }
}
