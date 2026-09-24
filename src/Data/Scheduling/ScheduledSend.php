<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Scheduling;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Actor;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Enums\ScheduledSendStatus;
use Linqelio\Laravel\Data\Read;

/**
 * One message to one contact, held until its moment.
 *
 * At `sendAt` it goes through send policy like any send; a limit moves it to
 * `nextAttemptAt` rather than failing it. `code`/`detail` say why one failed.
 */
final readonly class ScheduledSend
{
    /**
     * @param  array<string, mixed>  $content
     * @param  string|null  $messageId  the message it became, once sent
     */
    public function __construct(
        public string $id,
        public string $contactId,
        public ScheduledSendStatus $status,
        public ?DateTimeImmutable $sendAt,
        public MessageType $type,
        public array $content = [],
        public ?string $channelId = null,
        public ?DateTimeImmutable $nextAttemptAt = null,
        public ?string $replyTo = null,
        public int $attempts = 0,
        public ?string $code = null,
        public ?string $detail = null,
        public ?string $messageId = null,
        public ?Actor $createdBy = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $sentAt = null,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Read::string($data, 'id'),
            contactId: Read::string($data, 'contactId'),
            status: ScheduledSendStatus::tryFrom(Read::string($data, 'status')) ?? ScheduledSendStatus::Scheduled,
            sendAt: Read::date($data, 'sendAt'),
            type: MessageType::tryFrom(Read::string($data, 'type')) ?? MessageType::Text,
            content: Read::map($data, 'content'),
            channelId: Read::stringOrNull($data, 'channelId'),
            nextAttemptAt: Read::date($data, 'nextAttemptAt'),
            replyTo: Read::stringOrNull($data, 'replyTo'),
            attempts: Read::int($data, 'attempts'),
            code: Read::stringOrNull($data, 'code'),
            detail: Read::stringOrNull($data, 'detail'),
            messageId: Read::stringOrNull($data, 'messageId'),
            createdBy: Actor::fromNullable(Read::object($data, 'createdBy')),
            createdAt: Read::date($data, 'createdAt'),
            updatedAt: Read::date($data, 'updatedAt'),
            sentAt: Read::date($data, 'sentAt'),
            finishedAt: Read::date($data, 'finishedAt'),
        );
    }
}
