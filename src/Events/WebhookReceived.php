<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Every verified delivery, untyped.
 *
 * The platform's event registry is additive: new event types appear without this
 * package knowing about them. Rather than making an application wait for a
 * release to react to one, this carries the raw payload straight through.
 *
 * Fires alongside the typed events, not instead of them — a listener for
 * MessageReceived does not need this one.
 */
final class WebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function type(): ?string
    {
        $type = $this->payload['event'] ?? $this->payload['eventType'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }
}
