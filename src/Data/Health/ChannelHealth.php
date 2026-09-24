<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Health;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\HealthRating;
use Linqelio\Laravel\Data\Read;

/**
 * How healthy a channel is — the early warning before a messenger bans a number.
 *
 * The score is explained, not just given: every component says how much it cost
 * and why, so "72, warning" can be answered with "delivery failures doubled".
 */
final readonly class ChannelHealth
{
    /**
     * @param  bool  $computed  false until there was enough traffic to score it
     * @param  string|null  $cap  a send cap the score currently imposes, if any
     * @param  array<int, HealthComponent>  $components
     * @param  array<int, string>  $reasons  machine reasons behind the rating
     */
    public function __construct(
        public string $channelId,
        public string $kind,
        public HealthRating $rating,
        public int $score = 0,
        public bool $computed = false,
        public bool $connected = false,
        public ?string $cap = null,
        public ?DateTimeImmutable $offlineSince = null,
        public array $components = [],
        public array $reasons = [],
        public ?DateTimeImmutable $computedAt = null,
        public ?DateTimeImmutable $checkedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channelId: Read::string($data, 'channelId'),
            kind: Read::string($data, 'kind'),
            rating: HealthRating::parse(Read::stringOrNull($data, 'rating')),
            score: Read::int($data, 'score'),
            computed: Read::bool($data, 'computed'),
            connected: Read::bool($data, 'connected'),
            cap: Read::stringOrNull($data, 'cap'),
            offlineSince: Read::date($data, 'offlineSince'),
            components: array_map(HealthComponent::fromArray(...), Read::objects($data, 'components')),
            reasons: Read::strings($data, 'reasons'),
            computedAt: Read::date($data, 'computedAt'),
            checkedAt: Read::date($data, 'checkedAt'),
        );
    }

    /** Somebody should look at it: a warning or worse, or it is offline. */
    public function needsAttention(): bool
    {
        return ! $this->connected
            || $this->rating === HealthRating::Warning
            || $this->rating === HealthRating::Critical;
    }
}
