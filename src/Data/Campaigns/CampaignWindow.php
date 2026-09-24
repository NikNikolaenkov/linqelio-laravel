<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Read;

/**
 * The local hours a campaign may send in (`HH:MM`, 24 h, in the campaign's time
 * zone). An `end` before `start` spans midnight. Outside it recipients wait for
 * the next opening; send policy's quiet hours apply on top.
 */
final readonly class CampaignWindow
{
    public function __construct(
        public string $start,
        public string $end,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromNullable(?array $data): ?self
    {
        return $data === null ? null : new self(Read::string($data, 'start'), Read::string($data, 'end'));
    }

    /**
     * @return array{start: string, end: string}
     */
    public function toArray(): array
    {
        return ['start' => $this->start, 'end' => $this->end];
    }
}
