<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Actor;
use Linqelio\Laravel\Data\Enums\ExportStatus;
use Linqelio\Laravel\Data\Read;

/**
 * A report export job: a CSV or XLSX file built in the background, kept until
 * `expiresAt`. Poll until {@see ExportStatus::isFinished()}, then download.
 */
final readonly class AnalyticsExport
{
    public function __construct(
        public string $id,
        public ExportStatus $status,
        public AnalyticsExportRequest $request,
        public string $timeZone = '',
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
            request: AnalyticsExportRequest::fromArray(Read::map($data, 'request')),
            timeZone: Read::string($data, 'timeZone'),
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
