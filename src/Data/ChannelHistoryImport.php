<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\ChannelHistoryImportStatus;

/**
 * A history import run of a Telegram account channel. Only `status` is always
 * there; a channel that never ran one reports {@see ChannelHistoryImportStatus::None}
 * and nothing else.
 */
final readonly class ChannelHistoryImport
{
    public function __construct(
        public ChannelHistoryImportStatus $status,
        public ?string $id = null,
        public ?int $days = null,
        public ?int $perChat = null,
        public int $dialogsDone = 0,
        public int $messagesImported = 0,
        public int $messagesSkipped = 0,
        public ?DateTimeImmutable $waitUntil = null,
        public ?string $error = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: ChannelHistoryImportStatus::tryFrom(Read::string($data, 'status')) ?? ChannelHistoryImportStatus::None,
            id: Read::stringOrNull($data, 'id'),
            days: Read::intOrNull($data, 'days'),
            perChat: Read::intOrNull($data, 'perChat'),
            dialogsDone: Read::int($data, 'dialogsDone'),
            messagesImported: Read::int($data, 'messagesImported'),
            messagesSkipped: Read::int($data, 'messagesSkipped'),
            waitUntil: Read::date($data, 'waitUntil'),
            error: Read::stringOrNull($data, 'error'),
            createdAt: Read::date($data, 'createdAt'),
            startedAt: Read::date($data, 'startedAt'),
            finishedAt: Read::date($data, 'finishedAt'),
        );
    }
}
