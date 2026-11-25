<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Chats;

/**
 * One person to add to or remove from a Telegram chat, by phone number.
 *
 * `name` names the contact the platform matches or creates for the phone (it
 * creates one only when an invite message has to be sent to a stranger).
 */
final readonly class ChatMember
{
    public function __construct(
        public string $phone,
        public ?string $name = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->name === null
            ? ['phone' => $this->phone]
            : ['phone' => $this->phone, 'name' => $this->name];
    }
}
