<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Chats;

use Linqelio\Laravel\Data\Read;

/**
 * The Telegram chats a channel's account administers.
 *
 * `complete` is false when the account's dialog list was not read to its end
 * (the scan stops at 2000 dialogs): chats may then be missing.
 */
final readonly class ChannelChats
{
    /**
     * @param  array<int, ChannelChat>  $chats
     */
    public function __construct(
        public string $channelId,
        public bool $complete,
        public array $chats = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channelId: Read::string($data, 'channelId'),
            complete: Read::bool($data, 'complete'),
            chats: array_map(ChannelChat::fromArray(...), Read::objects($data, 'items')),
        );
    }
}
