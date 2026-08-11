<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\RegisteredWebhook;
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
     * Every subscription registered here is signed, and there are three ways to
     * decide with what. Pass neither `$secretRef` nor `$secret` and the platform
     * mints a key, returning it on the result — the one and only time it is ever
     * shown. Pass `$secret` to sign with a key your receiver already expects; it
     * goes to the platform's secret store and no read returns it afterwards. Pass
     * `$secretRef` — a `secret://` reference — when you manage that store
     * yourself and the key is already in it.
     *
     * The two are mutually exclusive: passing both is a 400, because nothing then
     * says which key signs. And `$secret` is the KEY, not a reference — a
     * `secret://…` string passed there would be signed with literally, so it is
     * refused here rather than at the far end of a delivery nobody can verify.
     *
     * Leave `$eventTypes` empty to receive every type.
     *
     * @param  array<int, string>  $eventTypes
     *
     * @throws \InvalidArgumentException when both key arguments are given, or
     *                                   when $secret is a reference
     */
    public function register(
        string $url,
        array $eventTypes = [],
        ?string $secretRef = null,
        ?string $secret = null,
    ): RegisteredWebhook {
        if ($secretRef !== null && $secret !== null) {
            throw new \InvalidArgumentException(
                'Pass $secret or $secretRef, not both: nothing would say which key signs deliveries.'
            );
        }

        if ($secret !== null && str_starts_with($secret, 'secret://')) {
            throw new \InvalidArgumentException(
                'A secret:// value is a reference, not a key — pass it as $secretRef. '.
                'Sent as $secret it would be stored and signed with verbatim.'
            );
        }

        $body = array_filter([
            'url' => $url,
            'eventTypes' => $eventTypes === [] ? null : array_values($eventTypes),
            'secretRef' => $secretRef,
            'secret' => $secret,
        ], static fn ($v): bool => $v !== null);

        return RegisteredWebhook::fromArray($this->client->post('/webhooks', $body)->data);
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
