<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Chats;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\ChatMemberOutcome;
use Linqelio\Laravel\Data\Read;

/**
 * The outcome for one person of an add or remove call.
 *
 * `reason` stays a string: besides the platform's own reasons (`invalid_phone`,
 * `rate_limited`, `admin`, `admin_required`, `ban_not_lifted`, `send_policy`,
 * `premium_required`…) it carries Telegram's refusal codes in lower case.
 * `outcome` is null only for a value newer than this package.
 */
final readonly class ChatMemberResult
{
    public function __construct(
        public string $phone,
        public ?ChatMemberOutcome $outcome = null,
        public ?string $contactId = null,
        public ?string $providerId = null,
        public ?string $reason = null,
        public ?string $inviteLink = null,
        public ?DateTimeImmutable $inviteExpiresAt = null,
        public ?string $inviteMessageId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: Read::string($data, 'phone'),
            outcome: ChatMemberOutcome::tryFrom(Read::string($data, 'outcome')),
            contactId: Read::stringOrNull($data, 'contactId'),
            providerId: Read::stringOrNull($data, 'providerId'),
            reason: Read::stringOrNull($data, 'reason'),
            inviteLink: Read::stringOrNull($data, 'inviteLink'),
            inviteExpiresAt: Read::date($data, 'inviteExpiresAt'),
            inviteMessageId: Read::stringOrNull($data, 'inviteMessageId'),
        );
    }
}
