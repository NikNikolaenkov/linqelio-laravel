<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Imports;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Actor;
use Linqelio\Laravel\Data\Enums\ContactImportStatus;
use Linqelio\Laravel\Data\Read;

/**
 * A contact import job: a CSV file uploaded, mapped, and run in the background.
 *
 * Upload → `headers` and `sampleRows` come back → build an ImportMapping against
 * them → preview → start → poll until finished → read the per-row report.
 */
final readonly class ContactImport
{
    /**
     * @param  array<int, string>  $headers  the file's header row
     * @param  array<int, array<int, string>>  $sampleRows  the first rows, for building a mapping
     * @param  int  $mergeProposals  possible duplicates found, left for a person to decide
     */
    public function __construct(
        public string $id,
        public ContactImportStatus $status,
        public string $filename = '',
        public int $sizeBytes = 0,
        public array $headers = [],
        public int $totalRows = 0,
        public array $sampleRows = [],
        public ?ImportMapping $mapping = null,
        public int $processedRows = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $rejected = 0,
        public int $warnings = 0,
        public int $mergeProposals = 0,
        public int $consentsRecorded = 0,
        public ?Actor $createdBy = null,
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
        $rows = [];
        foreach (Read::objects($data, 'sampleRows') as $row) {
            $rows[] = array_values(array_map(
                static fn (mixed $cell): string => is_scalar($cell) ? (string) $cell : '',
                $row,
            ));
        }

        $mapping = Read::object($data, 'mapping');

        return new self(
            id: Read::string($data, 'id'),
            status: ContactImportStatus::tryFrom(Read::string($data, 'status')) ?? ContactImportStatus::Uploaded,
            filename: Read::string($data, 'filename'),
            sizeBytes: Read::int($data, 'sizeBytes'),
            headers: Read::strings($data, 'headers'),
            totalRows: Read::int($data, 'totalRows'),
            sampleRows: $rows,
            mapping: $mapping === null ? null : ImportMapping::fromArray($mapping),
            processedRows: Read::int($data, 'processedRows'),
            created: Read::int($data, 'created'),
            updated: Read::int($data, 'updated'),
            rejected: Read::int($data, 'rejected'),
            warnings: Read::int($data, 'warnings'),
            mergeProposals: Read::int($data, 'mergeProposals'),
            consentsRecorded: Read::int($data, 'consentsRecorded'),
            createdBy: Actor::fromNullable(Read::object($data, 'createdBy')),
            error: Read::stringOrNull($data, 'error'),
            createdAt: Read::date($data, 'createdAt'),
            startedAt: Read::date($data, 'startedAt'),
            finishedAt: Read::date($data, 'finishedAt'),
        );
    }

    /** The 0-based index of a header, for building a mapping by name. */
    public function columnOf(string $header): ?int
    {
        $index = array_search($header, $this->headers, true);

        return $index === false ? null : $index;
    }
}
