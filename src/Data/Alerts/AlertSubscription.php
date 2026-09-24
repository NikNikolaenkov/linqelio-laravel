<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Alerts;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\AlertDeliveryChannel;
use Linqelio\Laravel\Data\Enums\AlertSeverity;
use Linqelio\Laravel\Data\Enums\AlertType;
use Linqelio\Laravel\Data\Read;

/**
 * Where matching alerts are delivered.
 *
 * For a host application the useful one is `webhook`: alerts routed to one of
 * the cabinet's outbound webhooks, so they arrive where your other events do.
 */
final readonly class AlertSubscription
{
    /**
     * @param  array<int, AlertType>  $ruleTypes  empty = every type
     * @param  array<int, string>  $channelIds  empty = every channel
     * @param  bool  $own  a person's own subscription rather than a shared one
     */
    public function __construct(
        public string $id,
        public ?AlertDeliveryChannel $channel,
        public AlertSeverity $minSeverity,
        public array $ruleTypes = [],
        public array $channelIds = [],
        public bool $enabled = true,
        public bool $own = false,
        public ?string $accountId = null,
        public ?string $role = null,
        public ?string $webhookId = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $types = [];
        foreach (Read::strings($data, 'ruleTypes') as $type) {
            $parsed = AlertType::tryFrom($type);
            if ($parsed !== null) {
                $types[] = $parsed;
            }
        }

        return new self(
            id: Read::string($data, 'id'),
            channel: AlertDeliveryChannel::tryFrom(Read::string($data, 'channel')),
            minSeverity: AlertSeverity::tryFrom(Read::string($data, 'minSeverity')) ?? AlertSeverity::Info,
            ruleTypes: $types,
            channelIds: Read::strings($data, 'channelIds'),
            enabled: Read::bool($data, 'enabled', true),
            own: Read::bool($data, 'own'),
            accountId: Read::stringOrNull($data, 'accountId'),
            role: Read::stringOrNull($data, 'role'),
            webhookId: Read::stringOrNull($data, 'webhookId'),
            createdAt: Read::date($data, 'createdAt'),
            updatedAt: Read::date($data, 'updatedAt'),
        );
    }
}
