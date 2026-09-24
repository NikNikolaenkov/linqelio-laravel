<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Read;

/**
 * What an audience definition resolves to now. Nothing is stored.
 *
 * `matched` is capped at `limit + 1`: past the limit the exact number stops
 * mattering, because launch refuses the campaign either way.
 */
final readonly class AudienceCount
{
    public function __construct(
        public int $matched,
        public int $explicitRequested = 0,
        public int $explicitIncluded = 0,
        public bool $tooLarge = false,
        public int $limit = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            matched: Read::int($data, 'matched'),
            explicitRequested: Read::int($data, 'explicitRequested'),
            explicitIncluded: Read::int($data, 'explicitIncluded'),
            tooLarge: Read::bool($data, 'tooLarge'),
            limit: Read::int($data, 'limit'),
        );
    }
}
