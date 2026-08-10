<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Webhook;

/**
 * Outbound webhook subscriptions — where Linqelio delivers your events.
 *
 * Registration is usually a one-off at setup, but the rest of the lifecycle is
 * not: an endpoint breaks, a deployment moves, a tenant leaves. Those need to be
 * doable from your own application rather than by asking someone with database
 * access, which is why the whole surface is here and not just the read.
 */
final readonly class WebhooksResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * @return array<int, Webhook>
     */
    public function list(): array
    {
        return array_map(Webhook::fromArray(...), $this->client->get('/webhooks')->items());
    }

    /**
     * Subscribe an endpoint.
     *
     * `$secretRef` is a `secret://` reference to the HMAC key that signs
     * deliveries — never the key itself. Leave `$eventTypes` empty to receive
     * every type.
     *
     * @param  array<int, string>  $eventTypes
     */
    public function register(string $url, array $eventTypes = [], ?string $secretRef = null): Webhook
    {
        $body = array_filter([
            'url' => $url,
            'eventTypes' => $eventTypes === [] ? null : array_values($eventTypes),
            'secretRef' => $secretRef,
        ], static fn ($v): bool => $v !== null);

        return Webhook::fromArray($this->client->post('/webhooks', $body)->data);
    }

    /**
     * Stop delivering without unsubscribing. The url, event types and signing
     * key survive, so resuming does not mean handing out a new key.
     *
     * Reach for this when an endpoint is broken or noisy — deleting and
     * re-registering would rotate the secret as a side effect.
     */
    public function disable(string $id): Webhook
    {
        return $this->setStatus($id, 'disabled');
    }

    /** Resume delivery on a subscription that was disabled. */
    public function enable(string $id): Webhook
    {
        return $this->setStatus($id, 'active');
    }

    /**
     * Unsubscribe for good. The signing key goes with it.
     *
     * Idempotent: deleting an id that is already gone succeeds, so a retry after
     * a lost response is not an error.
     */
    public function delete(string $id): void
    {
        $this->client->delete("/webhooks/{$id}");
    }

    private function setStatus(string $id, string $status): Webhook
    {
        return Webhook::fromArray($this->client->patch("/webhooks/{$id}", ['status' => $status])->data);
    }
}
