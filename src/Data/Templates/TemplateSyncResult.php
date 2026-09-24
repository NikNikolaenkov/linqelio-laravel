<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Templates;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * What a template sync with Meta changed.
 */
final readonly class TemplateSyncResult
{
    public function __construct(
        public int $added = 0,
        public int $updated = 0,
        public int $removed = 0,
        public int $unchanged = 0,
        public int $total = 0,
        public ?DateTimeImmutable $syncedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            added: Read::int($data, 'added'),
            updated: Read::int($data, 'updated'),
            removed: Read::int($data, 'removed'),
            unchanged: Read::int($data, 'unchanged'),
            total: Read::int($data, 'total'),
            syncedAt: Read::date($data, 'syncedAt'),
        );
    }
}
