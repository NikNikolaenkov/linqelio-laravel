<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Imports;

use Linqelio\Laravel\Data\Read;

/**
 * The answer to cancelling an import. `changed: false` means it had already
 * finished (or was already cancelled) — a success, not a refusal.
 */
final readonly class ContactImportChange
{
    public function __construct(
        public ContactImport $import,
        public bool $changed,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            import: ContactImport::fromArray(Read::map($data, 'job')),
            changed: Read::bool($data, 'changed'),
        );
    }
}
