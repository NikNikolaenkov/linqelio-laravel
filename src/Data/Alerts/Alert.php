<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Alerts;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\AlertSeverity;
use Linqelio\Laravel\Data\Enums\AlertStatus;
use Linqelio\Laravel\Data\Enums\AlertType;
use Linqelio\Laravel\Data\Read;

/**
 * A business alert: something about a channel or the cabinet that a person
 * should know — a health drop, a disconnect, a daily limit, a quota running out.
 *
 * An alert that keeps firing is ONE alert with a growing `occurrence`, not a new
 * one each time. It resolves by itself when its condition goes away.
 */
final readonly class Alert
{
    /**
     * @param  AlertType|null  $type  null for a type newer than this package; see `typeValue`
     * @param  string  $typeValue  the raw type, always set
     * @param  float  $value  the measurement that raised it
     * @param  array<string, float>  $params  thresholds and figures behind it
     */
    public function __construct(
        public string $id,
        public ?AlertType $type,
        public string $typeValue,
        public AlertSeverity $severity,
        public AlertStatus $status,
        public ?string $channelId = null,
        public float $value = 0.0,
        public array $params = [],
        public int $occurrence = 1,
        public ?DateTimeImmutable $raisedAt = null,
        public ?DateTimeImmutable $lastSeenAt = null,
        public ?DateTimeImmutable $acknowledgedAt = null,
        public ?string $acknowledgedBy = null,
        public ?DateTimeImmutable $resolvedAt = null,
        public ?string $resolvedBy = null,
        public bool $autoResolved = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = Read::string($data, 'type');

        return new self(
            id: Read::string($data, 'id'),
            type: AlertType::tryFrom($type),
            typeValue: $type,
            severity: AlertSeverity::tryFrom(Read::string($data, 'severity')) ?? AlertSeverity::Info,
            status: AlertStatus::tryFrom(Read::string($data, 'status')) ?? AlertStatus::Open,
            channelId: Read::stringOrNull($data, 'channelId'),
            value: Read::float($data, 'value'),
            params: Read::floatMap($data, 'params'),
            occurrence: Read::int($data, 'occurrence', 1),
            raisedAt: Read::date($data, 'raisedAt'),
            lastSeenAt: Read::date($data, 'lastSeenAt'),
            acknowledgedAt: Read::date($data, 'acknowledgedAt'),
            acknowledgedBy: Read::stringOrNull($data, 'acknowledgedBy'),
            resolvedAt: Read::date($data, 'resolvedAt'),
            resolvedBy: Read::stringOrNull($data, 'resolvedBy'),
            autoResolved: Read::bool($data, 'autoResolved'),
        );
    }

    /** Open or acknowledged — its condition still holds. */
    public function isActive(): bool
    {
        return $this->status !== AlertStatus::Resolved;
    }
}
