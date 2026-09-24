<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Health;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\HealthRating;
use Linqelio\Laravel\Data\Read;

/**
 * A channel's health at one moment of its history.
 */
final readonly class HealthPoint
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public ?DateTimeImmutable $at,
        public int $score,
        public HealthRating $rating,
        public bool $connected = false,
        public array $reasons = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            at: Read::date($data, 'at'),
            score: Read::int($data, 'score'),
            rating: HealthRating::parse(Read::stringOrNull($data, 'rating')),
            connected: Read::bool($data, 'connected'),
            reasons: Read::strings($data, 'reasons'),
        );
    }
}
