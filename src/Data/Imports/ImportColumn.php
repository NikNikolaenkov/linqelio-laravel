<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Imports;

use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\ContactImportTarget;
use Linqelio\Laravel\Data\Read;

/**
 * Where one CSV column of an import goes. `column` is its 0-based index in the
 * uploaded file's `headers`.
 *
 * Build one with the named constructors — each target needs its own companion
 * (a channel kind for an identity, a system for a host ref, a field key, a
 * channel for consent), and they say which.
 */
final readonly class ImportColumn
{
    public function __construct(
        public int $column,
        public ContactImportTarget $target,
        public ?ChannelKind $channelKind = null,
        public ?string $field = null,
        public ?string $channelId = null,
        public ?string $system = null,
    ) {}

    /** An address on one channel kind: a phone, a username, a provider id. */
    public static function identity(int $column, ChannelKind $kind): self
    {
        return new self($column, ContactImportTarget::Identity, channelKind: $kind);
    }

    /** Your own record id in `$system` (e.g. `crm`), linked as a host ref. */
    public static function hostRef(int $column, string $system): self
    {
        return new self($column, ContactImportTarget::HostRef, system: $system);
    }

    public static function name(int $column): self
    {
        return new self($column, ContactImportTarget::Name);
    }

    /** A typed contact field, by its key. */
    public static function field(int $column, string $key): self
    {
        return new self($column, ContactImportTarget::Field, field: $key);
    }

    public static function tags(int $column): self
    {
        return new self($column, ContactImportTarget::Tags);
    }

    /** A yes/no column recording consent on one channel. */
    public static function consent(int $column, string $channelId): self
    {
        return new self($column, ContactImportTarget::Consent, channelId: $channelId);
    }

    public static function ignore(int $column): self
    {
        return new self($column, ContactImportTarget::Ignore);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            column: Read::int($data, 'column'),
            target: ContactImportTarget::tryFrom(Read::string($data, 'target')) ?? ContactImportTarget::Ignore,
            channelKind: ChannelKind::tryFrom(Read::string($data, 'channelKind')),
            field: Read::stringOrNull($data, 'field'),
            channelId: Read::stringOrNull($data, 'channelId'),
            system: Read::stringOrNull($data, 'system'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Read::compact([
            'column' => $this->column,
            'target' => $this->target->value,
            'channelKind' => $this->channelKind?->value,
            'field' => $this->field,
            'channelId' => $this->channelId,
            'system' => $this->system,
        ]);
    }
}
