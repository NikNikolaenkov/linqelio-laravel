<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Policy;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\PolicyVerdict;
use Linqelio\Laravel\Data\Read;

/**
 * What send policy would say about a send NOW — without sending anything.
 *
 * A dry run: no rate budget, no counters, no journal row is consumed, so it is
 * safe to call while somebody is still typing. The flow it exists for:
 *
 *     $check = Linqelio::messages()->check($contactId, MessageType::Text, ['text' => $text]);
 *
 *     if (! $check->sendable) { ... show $check->findings, stop ... }
 *     if ($check->needsConfirmation()) { ... show the warnings, ask ... }
 *
 *     Linqelio::messages()->send($contactId, MessageType::Text, ['text' => $text],
 *         acknowledgedWarnings: $check->warningKeys);
 */
final readonly class SendPolicyCheck
{
    /**
     * @param  bool  $sendable  the verdict is `allow` or `warn`: the send would go through now
     * @param  array<int, string>  $warningKeys  exactly what to send back as
     *                                           `acknowledgedWarnings` once the
     *                                           warnings are confirmed; empty
     *                                           when there is nothing to confirm
     * @param  array<int, PolicyFinding>  $findings  every finding, in evaluation order
     * @param  array<int, PolicyMeter>  $meters  the current limits
     * @param  string  $channelId  the channel the send would leave through
     * @param  int  $cost  the rate budget this send would draw (media costs more)
     */
    public function __construct(
        public PolicyVerdict $verdict,
        public bool $sendable,
        public array $warningKeys,
        public array $findings,
        public array $meters,
        public string $channelId,
        public int $cost,
        public ?DateTimeImmutable $retryAt = null,
        public ?int $retryAfterSeconds = null,
        public ?DateTimeImmutable $evaluatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            verdict: PolicyVerdict::parse(Read::stringOrNull($data, 'verdict')),
            sendable: Read::bool($data, 'sendable'),
            warningKeys: Read::strings($data, 'warningKeys'),
            findings: PolicyFinding::listFrom($data, 'findings'),
            meters: array_map(PolicyMeter::fromArray(...), Read::objects($data, 'meters')),
            channelId: Read::string($data, 'channelId'),
            cost: Read::int($data, 'cost'),
            retryAt: Read::date($data, 'retryAt'),
            retryAfterSeconds: Read::intOrNull($data, 'retryAfterSeconds'),
            evaluatedAt: Read::date($data, 'evaluatedAt'),
        );
    }

    /** Sendable, but only once somebody has confirmed the warnings. */
    public function needsConfirmation(): bool
    {
        return $this->sendable && $this->warningKeys !== [];
    }

    /** Not now, but later: `retryAt` says when. */
    public function isDeferred(): bool
    {
        return $this->verdict === PolicyVerdict::Defer;
    }

    /**
     * The findings that need confirming.
     *
     * @return array<int, PolicyFinding>
     */
    public function warnings(): array
    {
        return array_values(array_filter($this->findings, static fn (PolicyFinding $f): bool => $f->isWarning()));
    }

    /**
     * The findings that stop the send — `defer` and `deny`.
     *
     * @return array<int, PolicyFinding>
     */
    public function blockers(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (PolicyFinding $f): bool => ! $f->verdict->isSendable(),
        ));
    }
}
