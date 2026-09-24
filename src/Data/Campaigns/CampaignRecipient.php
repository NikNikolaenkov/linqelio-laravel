<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\CampaignRecipientState;
use Linqelio\Laravel\Data\Read;

/**
 * One recipient of a launched campaign.
 *
 * `code` and `detail` say why a recipient was skipped or failed — no consent on
 * the channel, no address there, a template parameter the contact cannot fill.
 */
final readonly class CampaignRecipient
{
    /**
     * @param  string|null  $messageId  the message it produced, once sent — readable with `messages()->find()`
     */
    public function __construct(
        public string $contactId,
        public ?CampaignRecipientState $state,
        public int $seq = 0,
        public int $attempts = 0,
        public ?string $contactName = null,
        public ?DateTimeImmutable $nextAttemptAt = null,
        public ?string $code = null,
        public ?string $detail = null,
        public ?string $channelId = null,
        public ?string $messageId = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $sentAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contactId: Read::string($data, 'contactId'),
            state: CampaignRecipientState::tryFrom(Read::string($data, 'state')),
            seq: Read::int($data, 'seq'),
            attempts: Read::int($data, 'attempts'),
            contactName: Read::stringOrNull($data, 'contactName'),
            nextAttemptAt: Read::date($data, 'nextAttemptAt'),
            code: Read::stringOrNull($data, 'code'),
            detail: Read::stringOrNull($data, 'detail'),
            channelId: Read::stringOrNull($data, 'channelId'),
            messageId: Read::stringOrNull($data, 'messageId'),
            updatedAt: Read::date($data, 'updatedAt'),
            sentAt: Read::date($data, 'sentAt'),
        );
    }
}
