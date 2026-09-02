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
 *
 * `note` tells a recorded voice message or video note from a file of the same
 * type. Without reading it, an incoming "кружечок" is indistinguishable from a
 * clip somebody attached — and without sending it, one cannot be sent at all.
 */
final readonly class MediaContent
{
    /**
     * @param  bool  $note  A message RECORDED in the client — a voice message or
     *                      a video note ("кружечок") — rather than an audio or
     *                      video FILE that happens to be attached. It is a
     *                      presentation hint, not a type: the message still goes
     *                      as `audio`/`video`, and a channel that cannot render
     *                      the form sends an ordinary attachment instead of
     *                      refusing the message. Telegram honours both over a
     *                      user account and the voice form over a bot; the round
     *                      frame degrades to a plain video there, because the Bot
     *                      API will not take a URL for a video note.
     */
    public function __construct(
        public string $url,
        public ?string $mime = null,
        public ?string $filename = null,
        public ?string $caption = null,
        public ?int $size = null,
        public bool $note = false,
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
            note: (bool) ($data['note'] ?? false),
        );
    }

    /**
     * Shape this as the `content` of a send command.
     *
     * @return array{media: array<string, mixed>}
     */
    public function toContent(?string $caption = null): array
    {
        $media = array_filter([
            'url' => $this->url,
            'mime' => $this->mime,
            'filename' => $this->filename,
            'caption' => $caption ?? $this->caption,
        ], static fn ($v): bool => $v !== null && $v !== '');

        // Only when true: `note: false` on every ordinary attachment would say
        // "deliberately not a note" on wire where the field simply does not
        // apply, and older platforms would carry a field they cannot read.
        if ($this->note) {
            $media['note'] = true;
        }

        return ['media' => $media];
    }
}
