<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Linqelio\Laravel\Events\MessageReceived;
use Linqelio\Laravel\Events\MessageStatusChanged;
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
     * @param  string  $eventType  the X-Linqelio-Event header; "" when the
     *                             platform predates it
     */
    public function __construct(
        private readonly array $payload,
        private readonly string $eventType = '',
    ) {}

    public function handle(): void
    {
        // The event registry grows additively, so a delivery this package does
        // not recognise is dropped quietly rather than retried and dead-lettered;
        // WebhookReceived has already carried the raw body to anyone listening.
        if (! is_string($this->payload['messageId'] ?? null)) {
            return;
        }

        match ($this->eventType()) {
            'message.status' => $this->handleStatus(),
            default => $this->handleInbound(),
        };
    }

    /**
     * Which event this delivery is.
     *
     * Taken from X-Linqelio-Event — the platform's own declaration — rather than
     * sniffed from the payload's shape. Both message events carry `messageId`,
     * and telling them apart by which OTHER fields happen to be present is the
     * kind of guess that breaks the first time a payload gains a field.
     *
     * The fallback exists for a platform that predates the header, and for it a
     * `status` field is a safe tell: message.inbound has never carried one.
     */
    private function eventType(): string
    {
        if ($this->eventType !== '') {
            return $this->eventType;
        }

        return isset($this->payload['status']) ? 'message.status' : 'message.inbound';
    }

    private function handleInbound(): void
    {
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

    private function handleStatus(): void
    {
        $event = MessageStatusChanged::fromPayload($this->payload);

        // The projection is advanced first, for the same reason inbound records
        // first: a listener that renders a thread should not see a message still
        // marked queued after being told it failed.
        if ($this->projectionEnabled()) {
            LinqelioMessage::recordStatus($event);
        }

        event($event);
    }

    private function projectionEnabled(): bool
    {
        return (bool) config('linqelio.projection.enabled', true);
    }
}
