<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * A campaign rehearsed without sending: who would receive it, who would be
 * skipped and why, and how long it would take.
 *
 * Nothing is enqueued and no send-policy counter is consumed. The one thing a
 * dry run records is WHEN it ran, because launch requires a dry run newer than
 * the draft's last change. Launch problems (an empty audience, a start in the
 * past, a template that does not fit) are reported in `problems` instead of
 * refused, so they can be shown together.
 */
final readonly class CampaignDryRun
{
    /**
     * @param  array<string, string>  $problems  field => reason; empty when launchable
     * @param  array<int, array{reason: string, code: string, count: int}>  $blocked  why recipients would be skipped
     * @param  array<int, DryRunChannel>  $channels
     * @param  array<int, DryRunSample>  $samples
     * @param  array<string, array<string, string>>  $warnings  warning key => its params
     */
    public function __construct(
        public string $campaignId,
        public bool $launchable,
        public AudienceCount $audience,
        public int $reachable,
        public DryRunEstimate $estimate,
        public array $problems = [],
        public array $blocked = [],
        public array $channels = [],
        public array $samples = [],
        public array $warnings = [],
        public ?DateTimeImmutable $evaluatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $problems = [];
        foreach (Read::objects($data, 'problems') as $problem) {
            $problems[Read::string($problem, 'field')] = Read::string($problem, 'reason');
        }

        $blocked = [];
        foreach (Read::objects($data, 'blocked') as $row) {
            $blocked[] = [
                'reason' => Read::string($row, 'reason'),
                'code' => Read::string($row, 'code'),
                'count' => Read::int($row, 'count'),
            ];
        }

        $warnings = [];
        foreach (Read::objects($data, 'warnings') as $warning) {
            $warnings[Read::string($warning, 'key')] = Read::stringMap($warning, 'params');
        }

        return new self(
            campaignId: Read::string($data, 'campaignId'),
            launchable: Read::bool($data, 'launchable'),
            audience: AudienceCount::fromArray(Read::map($data, 'audience')),
            reachable: Read::int($data, 'reachable'),
            estimate: DryRunEstimate::fromArray(Read::map($data, 'estimate')),
            problems: $problems,
            blocked: $blocked,
            channels: array_map(DryRunChannel::fromArray(...), Read::objects($data, 'channels')),
            samples: array_map(DryRunSample::fromArray(...), Read::objects($data, 'samples')),
            warnings: $warnings,
            evaluatedAt: Read::date($data, 'evaluatedAt'),
        );
    }

    /** Recipients that would be skipped, summed over every reason. */
    public function blockedCount(): int
    {
        return (int) array_sum(array_column($this->blocked, 'count'));
    }
}
