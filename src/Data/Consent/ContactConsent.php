<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Consent;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Actor;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\ConsentSource;
use Linqelio\Laravel\Data\Enums\ConsentStatus;
use Linqelio\Laravel\Data\Read;

/**
 * Whether a contact may be messaged on one channel, and on whose word (ADR-0084).
 *
 * Consent is per CHANNEL, not per person: agreeing to hear from a Telegram bot
 * says nothing about a WhatsApp number. While a cabinet enforces consent, a send
 * on a channel without an active one is refused with `policy.consent_missing`.
 */
final readonly class ContactConsent
{
    /**
     * @param  ConsentSource|null  $source  who recorded the grant; `host_api` for
     *                                      anything recorded through this package
     * @param  string|null  $evidence  where the consent came from — a form id, a
     *                                 CRM record — never personal data
     */
    public function __construct(
        public string $channelId,
        public ?ChannelKind $channelKind,
        public ConsentStatus $status,
        public ?ConsentSource $source = null,
        public ?DateTimeImmutable $grantedAt = null,
        public ?Actor $grantedBy = null,
        public ?string $evidence = null,
        public ?DateTimeImmutable $revokedAt = null,
        public ?Actor $revokedBy = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channelId: Read::string($data, 'channelId'),
            channelKind: ChannelKind::tryFrom(Read::string($data, 'channelKind')),
            status: ConsentStatus::tryFrom(Read::string($data, 'status')) ?? ConsentStatus::Missing,
            source: ConsentSource::tryFrom(Read::string($data, 'source')),
            grantedAt: Read::date($data, 'grantedAt'),
            grantedBy: Actor::fromNullable(Read::object($data, 'grantedBy')),
            evidence: Read::stringOrNull($data, 'evidence'),
            revokedAt: Read::date($data, 'revokedAt'),
            revokedBy: Actor::fromNullable(Read::object($data, 'revokedBy')),
            updatedAt: Read::date($data, 'updatedAt'),
        );
    }

    public function isGranted(): bool
    {
        return $this->status === ConsentStatus::Granted;
    }

    /**
     * Revoked is not the same as missing: a revocation also holds against a
     * later automatic grant from an inbound message, and only an explicit grant
     * lifts it.
     */
    public function isRevoked(): bool
    {
        return $this->status === ConsentStatus::Revoked;
    }
}
