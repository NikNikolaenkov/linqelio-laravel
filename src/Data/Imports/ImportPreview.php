<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Imports;

use Linqelio\Laravel\Data\Read;

/**
 * A mapping tried on the first rows of an import, without writing anything.
 */
final readonly class ImportPreview
{
    /**
     * @param  array<int, array{row: int, severity: string, code: string, column: ?string, detail: ?string, contactIds: array<int, string>}>  $issues
     */
    public function __construct(
        public int $rowsChecked,
        public int $totalRows = 0,
        public int $valid = 0,
        public int $wouldCreate = 0,
        public int $wouldUpdate = 0,
        public int $rejected = 0,
        public int $mergeProposals = 0,
        public int $withConsent = 0,
        public array $issues = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $issues = [];
        foreach (Read::objects($data, 'issues') as $issue) {
            $issues[] = [
                'row' => Read::int($issue, 'row'),
                'severity' => Read::string($issue, 'severity'),
                'code' => Read::string($issue, 'code'),
                'column' => Read::stringOrNull($issue, 'column'),
                'detail' => Read::stringOrNull($issue, 'detail'),
                'contactIds' => Read::strings($issue, 'contactIds'),
            ];
        }

        return new self(
            rowsChecked: Read::int($data, 'rowsChecked'),
            totalRows: Read::int($data, 'totalRows'),
            valid: Read::int($data, 'valid'),
            wouldCreate: Read::int($data, 'wouldCreate'),
            wouldUpdate: Read::int($data, 'wouldUpdate'),
            rejected: Read::int($data, 'rejected'),
            mergeProposals: Read::int($data, 'mergeProposals'),
            withConsent: Read::int($data, 'withConsent'),
            issues: $issues,
        );
    }

    /** Some row would be rejected. Warnings alone do not count. */
    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue['severity'] === 'error') {
                return true;
            }
        }

        return false;
    }
}
