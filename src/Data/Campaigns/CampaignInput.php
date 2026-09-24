<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use DateTimeInterface;
use Linqelio\Laravel\Data\Read;

/**
 * A campaign draft, as written: for a create, or as the FULL replacement of a
 * draft on update. A field left null on update is not "keep what is there" —
 * the draft is replaced, and the platform's default takes its place.
 */
final readonly class CampaignInput
{
    /**
     * @param  array<int, string>  $channelIds  the channels to send through, in
     *                                          order of preference
     * @param  DateTimeInterface|null  $startAt  null = start at launch
     * @param  string|null  $timeZone  IANA name the window is read in; the cabinet's by default
     * @param  int|null  $ratePerMinute  the campaign's own pace, on top of send policy
     * @param  int|null  $maxAttempts  how often a recipient is tried before it fails
     */
    public function __construct(
        public string $name,
        public array $channelIds,
        public CampaignContent $content,
        public ?CampaignAudience $audience = null,
        public ?DateTimeInterface $startAt = null,
        public ?string $timeZone = null,
        public ?CampaignWindow $window = null,
        public ?int $ratePerMinute = null,
        public ?int $maxAttempts = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Read::compact([
            'name' => $this->name,
            'channelIds' => array_values($this->channelIds),
            'content' => $this->content->toArray(),
            'audience' => $this->audience?->toArray(),
            'startAt' => Read::timestamp($this->startAt),
            'timeZone' => $this->timeZone,
            'window' => $this->window?->toArray(),
            'ratePerMinute' => $this->ratePerMinute,
            'maxAttempts' => $this->maxAttempts,
        ]);
    }
}
