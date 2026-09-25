<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use DateTimeInterface;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Alerts\Alert;
use Linqelio\Laravel\Data\Alerts\AlertChange;
use Linqelio\Laravel\Data\Alerts\AlertSubscription;
use Linqelio\Laravel\Data\Enums\AlertDeliveryChannel;
use Linqelio\Laravel\Data\Enums\AlertSeverity;
use Linqelio\Laravel\Data\Enums\AlertState;
use Linqelio\Laravel\Data\Enums\AlertStatus;
use Linqelio\Laravel\Data\Enums\AlertType;
use Linqelio\Laravel\Data\Read;

/**
 * Business alerts — a channel's health dropping, a disconnect, a daily limit, a
 * monthly quota running out — and where they are delivered.
 *
 * For a host application the natural delivery is a `webhook` subscription: the
 * alerts then arrive on the endpoint your other events already use.
 */
final readonly class AlertsResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Newest first. Pages like every other list: pass the previous page's
     * `nextCursor` back as `$cursor`.
     *
     * `$state` = {@see AlertState::Active} asks for the alerts still needing
     * attention (open or acknowledged); `$status` narrows to one status.
     * Given both, both must hold.
     *
     * @param  AlertStatus|string|null  $status  one status. A string is
     *                                           DEPRECATED: pass the enum, and
     *                                           `$state: AlertState::Active`
     *                                           instead of `'active'`
     * @param  string|null  $cursor  the previous page's `nextCursor` (sent as `since`)
     * @param  DateTimeInterface|null  $before  only alerts raised strictly before this instant
     * @return array{alerts: array<int, Alert>, nextCursor: ?string}
     *
     * @throws \InvalidArgumentException for a status string that is neither an
     *                                   {@see AlertStatus} nor `'active'`
     */
    public function list(
        AlertStatus|string|null $status = null,
        ?string $channelId = null,
        ?string $cursor = null,
        ?int $limit = null,
        ?AlertState $state = null,
        ?DateTimeInterface $before = null,
    ): array {
        // The deprecated `status=active` becomes `state=active` here, so the
        // wire only ever carries the standard forms (issue #140).
        if ($status === 'active') {
            $status = null;
            $state ??= AlertState::Active;
        }

        if (is_string($status)) {
            $status = AlertStatus::tryFrom($status) ?? throw new \InvalidArgumentException(
                "Unknown alert status '{$status}': pass an AlertStatus, or \$state for active/all."
            );
        }

        $response = $this->client->get('/alerts', Read::compact([
            'status' => $status?->value,
            'state' => $state?->value,
            'channelId' => $channelId,
            'since' => $cursor,
            'before' => Read::timestamp($before),
            'limit' => $limit,
        ]));

        return [
            'alerts' => array_map(Alert::fromArray(...), $response->items()),
            // `pageInfo.nextCursor`; the deprecated top-level `nextCursor` only
            // for a platform older than issue #140.
            'nextCursor' => $response->nextCursor() ?? Read::stringOrNull($response->data, 'nextCursor'),
        ];
    }

    public function find(string $alertId): Alert
    {
        return Alert::fromArray($this->client->get("/alerts/{$alertId}")->data);
    }

    /**
     * "Seen it": the alert stays until its condition clears (it then resolves by
     * itself) and is not raised again meanwhile. Idempotent.
     */
    public function acknowledge(string $alertId): AlertChange
    {
        return AlertChange::fromArray($this->client->post("/alerts/{$alertId}/acknowledge")->data);
    }

    /** Close an alert by hand (needs channels:manage). Idempotent. */
    public function resolve(string $alertId): AlertChange
    {
        return AlertChange::fromArray($this->client->post("/alerts/{$alertId}/resolve")->data);
    }

    /**
     * @return array<int, AlertSubscription>
     */
    public function subscriptions(): array
    {
        return array_map(AlertSubscription::fromArray(...), $this->client->get('/alert-subscriptions')->items());
    }

    /**
     * Subscribe to alerts. Route them to one of the cabinet's webhooks with
     * `webhookId` (channel `webhook`), or to everyone holding a `role`
     * (`console` or `email`); both need settings:manage. One subscription per
     * target and delivery channel — a second answers
     * `alert.subscription_conflict`.
     *
     * A retry with the same `$idempotencyKey` replays the first answer rather
     * than meeting that conflict (ADR-0103).
     *
     * @param  array<int, AlertType>  $ruleTypes  empty = every type
     * @param  array<int, string>  $channelIds  empty = every channel
     */
    public function subscribe(
        AlertDeliveryChannel $channel,
        ?string $webhookId = null,
        ?string $role = null,
        ?AlertSeverity $minSeverity = null,
        array $ruleTypes = [],
        array $channelIds = [],
        ?bool $enabled = null,
        ?string $idempotencyKey = null,
    ): AlertSubscription {
        $response = $this->client->post('/alert-subscriptions', Read::compact([
            'channel' => $channel->value,
            'role' => $role,
            'webhookId' => $webhookId,
            'minSeverity' => $minSeverity?->value,
            'ruleTypes' => $ruleTypes === [] ? null : self::types($ruleTypes),
            'channelIds' => $channelIds === [] ? null : array_values($channelIds),
            'enabled' => $enabled,
        ]), idempotencyKey: $idempotencyKey);

        return AlertSubscription::fromArray($response->data);
    }

    /**
     * Change a subscription's filter, or switch it off. A null argument leaves
     * that part as it is; an empty array clears the filter (every type, every
     * channel).
     *
     * @param  array<int, AlertType>|null  $ruleTypes
     * @param  array<int, string>|null  $channelIds
     */
    public function updateSubscription(
        string $subscriptionId,
        ?AlertSeverity $minSeverity = null,
        ?array $ruleTypes = null,
        ?array $channelIds = null,
        ?bool $enabled = null,
    ): AlertSubscription {
        $response = $this->client->patch("/alert-subscriptions/{$subscriptionId}", Read::compact([
            'minSeverity' => $minSeverity?->value,
            'ruleTypes' => $ruleTypes === null ? null : self::types($ruleTypes),
            'channelIds' => $channelIds === null ? null : array_values($channelIds),
            'enabled' => $enabled,
        ]));

        return AlertSubscription::fromArray($response->data);
    }

    public function unsubscribe(string $subscriptionId): void
    {
        $this->client->delete("/alert-subscriptions/{$subscriptionId}");
    }

    /**
     * @param  array<int, AlertType>  $types
     * @return array<int, string>
     */
    private static function types(array $types): array
    {
        return array_values(array_map(static fn (AlertType $t): string => $t->value, $types));
    }
}
