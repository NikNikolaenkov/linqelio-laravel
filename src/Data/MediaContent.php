<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * The `media` payload of a message.
 *
 * On the way out, `url` points at whatever the platform's object store handed
 * back from an upload — providers fetch it themselves, which is why it has to be
 * a URL rather than bytes.
 *
 * On the way in, treat `url` as expired: it was presigned when the message was
 * stored and will not be fetchable later. Read the attachment through
 * `Linqelio::media()->fetch($messageId)` instead, which streams it from the
 * platform under your key.
 */
final readonly class MediaContent
{
    public function __construct(
        public string $url,
        public ?string $mime = null,
        public ?string $filename = null,
        public ?string $caption = null,
        public ?int $size = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: (string) ($data['url'] ?? ''),
            mime: isset($data['mime']) ? (string) $data['mime'] : null,
            filename: isset($data['filename']) ? (string) $data['filename'] : null,
            caption: isset($data['caption']) ? (string) $data['caption'] : null,
            size: isset($data['size']) ? (int) $data['size'] : null,
        );
    }

    /**
     * Shape this as the `content` of a send command.
     *
     * @return array{media: array<string, mixed>}
     */
    public function toContent(?string $caption = null): array
    {
        return ['media' => array_filter([
            'url' => $this->url,
            'mime' => $this->mime,
            'filename' => $this->filename,
            'caption' => $caption ?? $this->caption,
        ], static fn ($v): bool => $v !== null && $v !== '')];
    }
}
