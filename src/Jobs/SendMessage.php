<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Linqelio\Laravel\Client\IdempotencyKey;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Facades\Linqelio;
use Linqelio\Laravel\Models\LinqelioMessage;

/**
 * Send a message from a queue.
 *
 * The reason to prefer this over calling the API inline is not throughput — it
 * is that a send can fail for reasons that resolve themselves (a rate limit, a
 * provider hiccup) and a queue is where waiting belongs.
 *
 * The idempotency key is derived from the job's own uuid, so every retry of this
 * job carries the key its first attempt used. That is what stops a redelivery
 * from putting the same message on somebody's phone twice.
 */
final class SendMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        public readonly string $contactId,
        public readonly MessageType $type,
        public readonly array $content,
        public readonly ?string $channelId = null,
        public readonly ?string $idempotencyKey = null,
    ) {}

    public function handle(): void
    {
        $message = Linqelio::messages()->send(
            contactId: $this->contactId,
            type: $this->type,
            content: $this->content,
            channelId: $this->channelId,
            idempotencyKey: $this->idempotencyKey ?? IdempotencyKey::forJob((string) $this->job?->uuid()),
        );

        if ((bool) config('linqelio.projection.enabled', true)
            && (bool) config('linqelio.projection.outbound', true)) {
            LinqelioMessage::recordOutbound($message, $this->channelId ?? '', $this->contactId);
        }
    }

    /**
     * Back off the way the platform asked, when it said so.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    /**
     * Give up early on anything a retry cannot fix.
     *
     * A rejected payload or an unaddressable contact will be rejected identically
     * next time; burning five attempts on it only delays the failure reaching
     * whoever needs to see it.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHour();
    }

    public function failed(\Throwable $e): void
    {
        // Nothing to clean up — the platform never saw a partial send. This hook
        // exists so an application can observe the failure with the exception's
        // error code intact rather than a generic queue error.
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['linqelio', 'contact:'.$this->contactId, 'type:'.$this->type->value];
    }
}
