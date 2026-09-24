<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Imports;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Actor;
use Linqelio\Laravel\Data\Enums\ExportStatus;
use Linqelio\Laravel\Data\Read;

/**
 * A contact export job: a CSV file built in the background.
 *
 * The file is kept until `expiresAt` and then swept; the job stays, as
 * `expired`. Poll until {@see ExportStatus::isFinished()}, then download.
 */
final readonly class ContactExport
{
    /**
     * @param  bool  $scoped  the key's channel scope narrowed the export
     */
    public function __construct(
        public string $id,
        public ExportStatus $status,
        public ContactExportFilter $filter,
        public bool $scoped = false,
        public int $rowCount = 0,
        public int $sizeBytes = 0,
        public ?Actor $createdBy = null,
        public ?string $error = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $finishedAt = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?string $downloadPath = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Read::string($data, 'id'),
            status: ExportStatus::tryFrom(Read::string($data, 'status')) ?? ExportStatus::Queued,
            filter: ContactExportFilter::fromArray(Read::map($data, 'filter')),
            scoped: Read::bool($data, 'scoped'),
            rowCount: Read::int($data, 'rowCount'),
            sizeBytes: Read::int($data, 'sizeBytes'),
            createdBy: Actor::fromNullable(Read::object($data, 'createdBy')),
            error: Read::stringOrNull($data, 'error'),
            createdAt: Read::date($data, 'createdAt'),
            finishedAt: Read::date($data, 'finishedAt'),
            expiresAt: Read::date($data, 'expiresAt'),
            downloadPath: Read::stringOrNull($data, 'downloadPath'),
        );
    }
}
