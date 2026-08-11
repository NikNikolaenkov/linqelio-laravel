<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\MessageStatus;
use Linqelio\Laravel\Data\Message;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * One of your outbound messages moved: sent, delivered, read or failed.
 *
 * This is how a send's outcome arrives. `messages()->send()` returns as soon as
 * the platform accepts the command — "handed over", not "delivered" — and the
 * provider is reached afterwards. Without this event the only way to learn what
 * happened was to re-read the message, so nobody did, and a failed send looked
 * exactly like a successful one.
 *
 * Subscribe to `message.status` when registering the webhook. Every transition is
 * delivered; filter with `eventTypes` if read receipts are more traffic than you
 * want, since on a busy cabinet they are the bulk of it.
 *
 * Like every delivery, the payload carries identifiers only — never the body.
 */
final class MessageStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    private ?Message $message = null;

    public function __construct(
        public readonly string $messageId,
        public readonly MessageStatus $status,
        public readonly ChannelKind $kind,
        public readonly string $channelId,
        public readonly string $chatId,
        public readonly string $contactRef,
        public readonly ?string $providerMessageId,
        /** Why it failed. Present on `failed` only. */
        public readonly ?string $reason,
        public readonly DateTimeImmutable $occurredAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $reason = isset($payload['reason']) ? (string) $payload['reason'] : null;

        return new self(
            messageId: (string) ($payload['messageId'] ?? ''),
            status: MessageStatus::tryFrom((string) ($payload['status'] ?? '')) ?? MessageStatus::Queued,
            kind: ChannelKind::tryFrom((string) ($payload['kind'] ?? '')) ?? ChannelKind::WaWeb,
            channelId: (string) ($payload['channelId'] ?? ''),
            chatId: (string) ($payload['chatId'] ?? ''),
            contactRef: (string) ($payload['contactRef'] ?? ''),
            providerMessageId: isset($payload['providerMsgId']) ? (string) $payload['providerMsgId'] : null,
            reason: $reason !== '' ? $reason : null,
            occurredAt: self::parseTime($payload['occurredAt'] ?? null),
        );
    }

    /**
     * True when this message will not be delivered and will not be retried.
     *
     * The transition worth acting on: a permanent send error means the recipient
     * was never reached and nothing further will happen on its own.
     */
    public function hasFailed(): bool
    {
        return $this->status === MessageStatus::Failed;
    }

    /** True when the message reached the recipient's device or was read. */
    public function reachedRecipient(): bool
    {
        return $this->status === MessageStatus::Delivered
            || $this->status === MessageStatus::Read;
    }

    /**
     * The full message, fetched on first use and memoised.
     *
     * One call. A listener that only needs `$event->status` and `$event->reason`
     * pays for nothing — which is most of them, since the event already carries
     * what a status change is usually about.
     */
    public function message(): ?Message
    {
        if ($this->message !== null) {
            return $this->message;
        }

        try {
            return $this->message = Linqelio::messages()->find($this->messageId);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseTime(mixed $raw): DateTimeImmutable
    {
        if (is_string($raw) && $raw !== '') {
            try {
                return new DateTimeImmutable($raw);
            } catch (\Exception) {
                // fall through to now
            }
        }

        return new DateTimeImmutable;
    }
}
