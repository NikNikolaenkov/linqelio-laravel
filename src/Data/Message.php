<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\MessageStatus;
use Linqelio\Laravel\Data\Enums\MessageType;

/**
 * One message, inbound or outbound.
 *
 * `id` is a ULID minted by the platform and is stable forever — it is the only
 * safe key to store on your side. Addresses are not: a chat id can be re-keyed
 * when the platform learns that a phone number and a messenger account are the
 * same person.
 */
final readonly class Message
{
    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        public string $id,
        public string $direction,
        public MessageType $type,
        public array $content,
        public DateTimeImmutable $timestamp,
        public ?MessageStatus $status = null,
        public ?string $providerMessageId = null,
        public ?string $author = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            direction: (string) ($data['direction'] ?? 'inbound'),
            type: MessageType::tryFrom((string) ($data['type'] ?? 'text')) ?? MessageType::Text,
            content: self::contentOf($data),
            timestamp: self::timestampOf($data),
            status: MessageStatus::tryFrom((string) ($data['status'] ?? '')),
            providerMessageId: isset($data['providerMsgId']) ? (string) $data['providerMsgId'] : null,
            author: isset($data['author']) ? (string) $data['author'] : null,
        );
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /** The text of a text message, or a media caption. */
    public function text(): ?string
    {
        $text = $this->content['text'] ?? ($this->content['media']['caption'] ?? null);

        return is_string($text) && $text !== '' ? $text : null;
    }

    public function media(): ?MediaContent
    {
        $media = $this->content['media'] ?? null;

        return is_array($media) ? MediaContent::fromArray($media) : null;
    }

    /**
     * A history entry may inline `text` beside `content` rather than nesting it;
     * normalise so callers never have to know which shape they were handed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function contentOf(array $data): array
    {
        $content = $data['content'] ?? null;
        if (is_array($content) && $content !== []) {
            return $content;
        }

        return isset($data['text']) ? ['text' => (string) $data['text']] : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function timestampOf(array $data): DateTimeImmutable
    {
        $raw = $data['ts']
            ?? ($data['timestamps']['created'] ?? null)
            ?? ($data['occurredAt'] ?? null);

        if (is_string($raw) && $raw !== '') {
            try {
                return new DateTimeImmutable($raw);
            } catch (\Exception) {
                // fall through — a malformed timestamp is not worth failing a read over
            }
        }

        return new DateTimeImmutable;
    }
}
