<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Chats;

use Linqelio\Laravel\Data\Enums\ChatMemberOutcome;
use Linqelio\Laravel\Data\Read;

/**
 * The per-person answer of adding people to, or removing them from, one chat.
 *
 * One refused person does not fail the call, so a 200 is not "everybody is in":
 * read `results`, one per requested person in request order. When Telegram asked
 * the account to wait part-way, the people not handled yet are `failed` with
 * reason `rate_limited`, and `retryAfterSeconds` says when to repeat for them.
 */
final readonly class ChatMembersResult
{
    /**
     * @param  array<int, ChatMemberResult>  $results
     */
    public function __construct(
        public string $chatId,
        public ?string $title = null,
        public array $results = [],
        public ?int $retryAfterSeconds = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            chatId: Read::string($data, 'chatId'),
            title: Read::stringOrNull($data, 'title'),
            results: array_map(ChatMemberResult::fromArray(...), Read::objects($data, 'results')),
            retryAfterSeconds: Read::intOrNull($data, 'retryAfterSeconds'),
        );
    }

    /**
     * The people it did not work for: `failed`, or an invite that was not sent.
     *
     * @return array<int, ChatMemberResult>
     */
    public function failed(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (ChatMemberResult $r): bool => $r->outcome === ChatMemberOutcome::Failed
                || $r->outcome === ChatMemberOutcome::InviteNotSent,
        ));
    }
}
