<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Enums\PolicyVerdict;
use Linqelio\Laravel\Data\Policy\PolicyFinding;
use Linqelio\Laravel\Data\Policy\PolicyMeter;
use Linqelio\Laravel\Data\Read;

/**
 * What send policy says about one sample send on one channel — the same answer
 * a single send's policy check gives, for one real recipient.
 */
final readonly class DryRunSample
{
    /**
     * @param  array<int, PolicyFinding>  $findings
     * @param  array<int, PolicyMeter>  $meters
     */
    public function __construct(
        public string $channelId,
        public string $contactId,
        public PolicyVerdict $verdict,
        public array $findings = [],
        public array $meters = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channelId: Read::string($data, 'channelId'),
            contactId: Read::string($data, 'contactId'),
            verdict: PolicyVerdict::parse(Read::stringOrNull($data, 'verdict')),
            findings: PolicyFinding::listFrom($data, 'findings'),
            meters: array_map(PolicyMeter::fromArray(...), Read::objects($data, 'meters')),
        );
    }
}
