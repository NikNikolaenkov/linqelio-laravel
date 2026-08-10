<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Linqelio\Laravel\Events\MessageReceived;
use Linqelio\Laravel\Models\LinqelioMessage;

/**
 * Turns a delivery into the typed event listeners actually want, and — when the
 * projection is on — records the message locally.
 *
 * Runs on a queue so the API reads it performs cannot slow the delivery down.
 */
final class ProcessWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function handle(): void
    {
        // A message.inbound payload is flat — its shape is the identification.
        // The event registry grows additively, so a delivery this package does
        // not recognise is dropped quietly rather than retried and dead-lettered;
        // WebhookReceived has already carried the raw body to anyone listening.
        if (! is_string($this->payload['messageId'] ?? null)) {
            return;
        }

        $event = MessageReceived::fromPayload($this->payload);

        // Record before dispatching. A listener that queries the projection —
        // rendering a thread, say — then sees this message already in it, rather
        // than racing the write.
        if ($this->projectionEnabled()) {
            LinqelioMessage::recordInbound($event);
        }

        // event() rather than dispatch(): it hands listeners THIS instance, so
        // the contact and message it may already have fetched stay memoised.
        event($event);
    }

    private function projectionEnabled(): bool
    {
        return (bool) config('linqelio.projection.enabled', true);
    }
}
