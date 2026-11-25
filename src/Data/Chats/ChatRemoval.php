<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Chats;

use Linqelio\Laravel\Data\Read;

/**
 * The per-chat answer of removing one person from the channel's chats.
 *
 * Not finished until `complete` is true: chats that were rate-limited or ran out
 * of time come back pending. The operation takes no Idempotency-Key because
 * repeating it is how it is finished; a chat already handled answers
 * `not_member` the second time. Wait `retryAfterSeconds` when it is set.
 */
final readonly class ChatRemoval
{
    /**
     * @param  array<int, ChatRemovalResult>  $results
     */
    public function __construct(
        public string $phone,
        public bool $complete,
        public array $results = [],
        public ?string $contactId = null,
        public ?string $providerId = null,
        public ?int $retryAfterSeconds = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: Read::string($data, 'phone'),
            complete: Read::bool($data, 'complete'),
            results: array_map(ChatRemovalResult::fromArray(...), Read::objects($data, 'results')),
            contactId: Read::stringOrNull($data, 'contactId'),
            providerId: Read::stringOrNull($data, 'providerId'),
            retryAfterSeconds: Read::intOrNull($data, 'retryAfterSeconds'),
        );
    }
}
