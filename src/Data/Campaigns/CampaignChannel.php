<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Read;

/**
 * One of a campaign's channels, as the dispatcher sees it right now.
 */
final readonly class CampaignChannel
{
    /**
     * @param  bool  $live  the channel is connected and may send
     * @param  string|null  $holdCode  why the channel is holding its recipients back, when it is
     */
    public function __construct(
        public string $channelId,
        public ?ChannelKind $kind,
        public int $position = 0,
        public bool $live = false,
        public ?DateTimeImmutable $nextSendAt = null,
        public ?string $holdCode = null,
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
            nextSendAt: Read::date($data, 'nextSendAt'),
            holdCode: Read::stringOrNull($data, 'holdCode'),
        );
    }
}
