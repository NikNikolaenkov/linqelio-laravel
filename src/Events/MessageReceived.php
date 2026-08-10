<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Linqelio\Laravel\Data\Contact;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Message;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Somebody sent a message to one of your channels.
 *
 * The webhook carries routing identifiers only — never the message body. That is
 * deliberate on the platform's side: the body can contain anything a customer
 * typed or attached, and shipping it to every registered endpoint would spread
 * that further than the API's own access rules reach.
 *
 * So this event arrives with the envelope, and {@see self::message()} fetches
 * the content when you actually want it. A listener that only queues work off
 * `$event->messageId` costs nothing; one that needs the text pays for one call,
 * memoised for the rest of the request.
 */
final class MessageReceived
{
    use Dispatchable;
    use SerializesModels;

    private ?Message $message = null;

    private ?Contact $contact = null;

    public function __construct(
        public readonly string $messageId,
        public readonly ChannelKind $kind,
        public readonly string $channelId,
        public readonly string $chatId,
        public readonly string $contactRef,
        public readonly MessageType $type,
        public readonly DateTimeImmutable $occurredAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            messageId: (string) ($payload['messageId'] ?? ''),
            kind: ChannelKind::tryFrom((string) ($payload['kind'] ?? '')) ?? ChannelKind::WaWeb,
            channelId: (string) ($payload['channelId'] ?? ''),
            chatId: (string) ($payload['chatId'] ?? ''),
            contactRef: (string) ($payload['contactRef'] ?? ''),
            type: MessageType::tryFrom((string) ($payload['type'] ?? 'text')) ?? MessageType::Text,
            occurredAt: self::parseTime($payload['occurredAt'] ?? null),
        );
    }

    /**
     * The contact who sent this, resolved from the provider address.
     *
     * The webhook names the other party the way the channel does — a phone, a
     * Telegram user id — while the API keys them by contact id, so this is a
     * lookup rather than a field.
     */
    public function contact(): ?Contact
    {
        if ($this->contact !== null) {
            return $this->contact;
        }

        try {
            return $this->contact = Linqelio::contacts()
                ->findByIdentity($this->contactRef, $this->kind->value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The full message, fetched on first use.
     *
     * Costs two calls: the contact has to be resolved before its history can be
     * read. Both are memoised for the life of the event, so a listener chain
     * that asks several times pays once.
     *
     * Returns null when the message cannot be retrieved. A listener should not
     * blow up over a missing body when the notification itself was valid and
     * signed — check for null and re-queue if the content is essential.
     */
    public function message(): ?Message
    {
        if ($this->message !== null) {
            return $this->message;
        }

        $contact = $this->contact();

        if ($contact === null) {
            return null;
        }

        try {
            $history = Linqelio::messages()->history($contact->id, limit: 20);
        } catch (\Throwable) {
            return null;
        }

        foreach ($history['messages'] as $message) {
            if ($message->id === $this->messageId) {
                return $this->message = $message;
            }
        }

        return null;
    }

    public function hasMedia(): bool
    {
        return $this->type->isMedia();
    }

    private static function parseTime(mixed $raw): DateTimeImmutable
    {
        if (is_string($raw) && $raw !== '') {
            try {
                return new DateTimeImmutable($raw);
            } catch (\Exception) {
                // fall through
            }
        }

        return new DateTimeImmutable;
    }
}
