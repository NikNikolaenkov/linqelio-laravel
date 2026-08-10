<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What a message carries.
 *
 * The `content` of a send command is keyed by this: `{"text": "..."}` for text,
 * `{"media": {...}}` for anything with a file behind it.
 */
enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';
    case Location = 'location';
    case Template = 'template';
    case Interactive = 'interactive';
    case System = 'system';

    /** Whether this type's content is a `media` payload with a URL behind it. */
    public function isMedia(): bool
    {
        return match ($this) {
            self::Image, self::Audio, self::Video, self::Document => true,
            default => false,
        };
    }
}
