<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Chats;

use Linqelio\Laravel\Data\Enums\ChatRole;
use Linqelio\Laravel\Data\Enums\ChatType;
use Linqelio\Laravel\Data\Read;

/**
 * A Telegram chat where the channel's account is the owner or an admin.
 *
 * `chatId` (`chat:<id>` or `channel:<id>`) is the address the member commands
 * take. `type` and `role` are null only for a value newer than this package.
 */
final readonly class ChannelChat
{
    public function __construct(
        public string $chatId,
        public ?ChatType $type = null,
        public string $title = '',
        public ?string $username = null,
        public ?int $membersCount = null,
        public ?ChatRole $role = null,
        public bool $canInvite = false,
        public bool $canRemove = false,
        public ?string $conversationId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            chatId: Read::string($data, 'chatId'),
            type: ChatType::tryFrom(Read::string($data, 'type')),
            title: Read::string($data, 'title'),
            username: Read::stringOrNull($data, 'username'),
            membersCount: Read::intOrNull($data, 'membersCount'),
            role: ChatRole::tryFrom(Read::string($data, 'role')),
            canInvite: Read::bool($data, 'canInvite'),
            canRemove: Read::bool($data, 'canRemove'),
            conversationId: Read::stringOrNull($data, 'conversationId'),
        );
    }
}
