<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Read;

/**
 * One channel's share of a dry-run campaign: who it would carry, and how fast.
 */
final readonly class DryRunChannel
{
    /**
     * @param  int  $addressable  matched recipients with an address on this channel
     * @param  int  $assigned  recipients this channel would actually carry
     * @param  float  $perMinute  its effective pace
     * @param  string  $paceLimitedBy  what sets that pace (campaign rate, warmup, ...)
     */
    public function __construct(
        public string $channelId,
        public ?ChannelKind $kind,
        public int $position = 0,
        public bool $live = false,
        public int $addressable = 0,
        public int $assigned = 0,
        public int $blocked = 0,
        public float $perMinute = 0.0,
        public string $paceLimitedBy = '',
        public ?int $dailyLimit = null,
        public ?int $hourlyLimit = null,
        public ?DateTimeImmutable $finishesAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channelId: Read::string($data, 'channelId'),
            kind: ChannelKind::tryFrom(Read::string($data, 'kind')),
            position: Read::int($data, 'position'),
            live: Read::bool($data, 'live'),
            addressable: Read::int($data, 'addressable'),
            assigned: Read::int($data, 'assigned'),
            blocked: Read::int($data, 'blocked'),
            perMinute: Read::float($data, 'perMinute'),
            paceLimitedBy: Read::string($data, 'paceLimitedBy'),
            dailyLimit: Read::intOrNull($data, 'dailyLimit'),
            hourlyLimit: Read::intOrNull($data, 'hourlyLimit'),
            finishesAt: Read::date($data, 'finishesAt'),
        );
    }
}
