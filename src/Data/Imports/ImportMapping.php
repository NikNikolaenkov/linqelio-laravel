<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Imports;

use Linqelio\Laravel\Data\Read;

/**
 * Which column of an import goes where.
 *
 * Needs at least one `identity` or `hostRef` column — something to find or
 * create the contact by — and at least one `consent` column: an import is how a
 * cabinet brings in people it may message, and it has to say on whose word.
 */
final readonly class ImportMapping
{
    /** @var array<int, ImportColumn> */
    public array $columns;

    public function __construct(ImportColumn ...$columns)
    {
        $this->columns = array_values($columns);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...array_map(ImportColumn::fromArray(...), Read::objects($data, 'columns')));
    }

    /**
     * @return array{columns: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['columns' => array_map(static fn (ImportColumn $c): array => $c->toArray(), $this->columns)];
    }
}
