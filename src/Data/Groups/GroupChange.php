<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Groups;

use Linqelio\Laravel\Data\Enums\GroupMemberFailureReason;
use Linqelio\Laravel\Data\Read;

/**
 * What a group change did on the messenger.
 *
 * A change is partial by nature: the messenger may refuse one member and accept
 * the rest, so the call succeeds and `failed` names who did not make it, and
 * why. Check it — a success is not "everybody is in".
 */
final readonly class GroupChange
{
    /**
     * @param  array<int, GroupMemberOutcome>  $requested  everyone the change was asked for
     * @param  array<int, GroupMemberOutcome>  $failed  those it did not happen for
     * @param  bool  $membersRefreshed  the participant list was re-read afterwards
     */
    public function __construct(
        public string $conversationId,
        public string $chatId,
        public ?string $subject = null,
        public array $requested = [],
        public array $failed = [],
        public bool $membersRefreshed = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            conversationId: Read::string($data, 'conversationId'),
            chatId: Read::string($data, 'chatId'),
            subject: Read::stringOrNull($data, 'subject'),
            requested: self::outcomes($data, 'requested'),
            failed: self::outcomes($data, 'failed'),
            membersRefreshed: Read::bool($data, 'membersRefreshed'),
        );
    }

    public function isComplete(): bool
    {
        return $this->failed === [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, GroupMemberOutcome>
     */
    private static function outcomes(array $data, string $key): array
    {
        $out = [];
        foreach (Read::objects($data, $key) as $item) {
            $out[] = new GroupMemberOutcome(
                contactId: Read::stringOrNull($item, 'contactId'),
                providerId: Read::stringOrNull($item, 'providerId'),
                reason: GroupMemberFailureReason::tryFrom(Read::string($item, 'reason')),
            );
        }

        return $out;
    }
}
