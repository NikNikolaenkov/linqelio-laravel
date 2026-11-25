<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Chats;

use Linqelio\Laravel\Data\Enums\ChatRemovalOutcome;
use Linqelio\Laravel\Data\Read;

/**
 * The outcome of removing the person from one chat. `outcome` is null only for
 * a value newer than this package; `reason` is a string for the same reason as
 * {@see ChatMemberResult::$reason}.
 */
final readonly class ChatRemovalResult
{
    public function __construct(
        public string $chatId,
        public ?string $title = null,
        public ?ChatRemovalOutcome $outcome = null,
        public ?string $reason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            chatId: Read::string($data, 'chatId'),
            title: Read::stringOrNull($data, 'title'),
            outcome: ChatRemovalOutcome::tryFrom(Read::string($data, 'outcome')),
            reason: Read::stringOrNull($data, 'reason'),
        );
    }
}
