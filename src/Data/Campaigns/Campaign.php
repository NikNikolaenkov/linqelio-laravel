<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Actor;
use Linqelio\Laravel\Data\Enums\CampaignPauseReason;
use Linqelio\Laravel\Data\Enums\CampaignStatus;
use Linqelio\Laravel\Data\Read;

/**
 * A bulk send: one message to an audience, through one or more channels, paced
 * by send policy in its campaign mode.
 */
final readonly class Campaign
{
    /**
     * @param  array<int, CampaignChannel>  $channels
     * @param  DateTimeImmutable|null  $dryRunAt  the last dry run; launch requires
     *                                            one newer than the draft's last change
     */
    public function __construct(
        public string $id,
        public string $name,
        public CampaignStatus $status,
        public array $channels,
        public CampaignContent $content,
        public CampaignAudience $audience,
        public CampaignProgress $progress,
        public ?CampaignPauseReason $pauseReason = null,
        public ?DateTimeImmutable $startAt = null,
        public string $timeZone = '',
        public ?CampaignWindow $window = null,
        public int $ratePerMinute = 0,
        public int $maxAttempts = 0,
        public ?Actor $createdBy = null,
        public ?string $error = null,
        public ?DateTimeImmutable $dryRunAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $launchedAt = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $pausedAt = null,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Read::string($data, 'id'),
            name: Read::string($data, 'name'),
            status: CampaignStatus::tryFrom(Read::string($data, 'status')) ?? CampaignStatus::Draft,
            channels: array_map(CampaignChannel::fromArray(...), Read::objects($data, 'channels')),
            content: CampaignContent::fromArray(Read::map($data, 'content')),
            audience: CampaignAudience::fromArray(Read::map($data, 'audience')),
            progress: CampaignProgress::fromArray(Read::map($data, 'progress')),
            pauseReason: CampaignPauseReason::tryFrom(Read::string($data, 'pauseReason')),
            startAt: Read::date($data, 'startAt'),
            timeZone: Read::string($data, 'timeZone'),
            window: CampaignWindow::fromNullable(Read::object($data, 'window')),
            ratePerMinute: Read::int($data, 'ratePerMinute'),
            maxAttempts: Read::int($data, 'maxAttempts'),
            createdBy: Actor::fromNullable(Read::object($data, 'createdBy')),
            error: Read::stringOrNull($data, 'error'),
            dryRunAt: Read::date($data, 'dryRunAt'),
            createdAt: Read::date($data, 'createdAt'),
            updatedAt: Read::date($data, 'updatedAt'),
            launchedAt: Read::date($data, 'launchedAt'),
            startedAt: Read::date($data, 'startedAt'),
            pausedAt: Read::date($data, 'pausedAt'),
            finishedAt: Read::date($data, 'finishedAt'),
        );
    }

    /**
     * Whether launch would be refused with `campaign.dry_run_required`: a draft
     * needs a dry run newer than its last change.
     */
    public function needsDryRun(): bool
    {
        if ($this->dryRunAt === null) {
            return true;
        }

        return $this->updatedAt !== null && $this->dryRunAt < $this->updatedAt;
    }
}
