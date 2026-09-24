<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Fields;

use Linqelio\Laravel\Data\Enums\FieldValueSource;
use Linqelio\Laravel\Data\Read;

/**
 * A contact's typed field values, each with who wrote it.
 *
 * Values arrive as JSON gives them — a `number` field as int or float, a `bool`
 * as bool, a `multi_enum` as a list, a `date` as `YYYY-MM-DD` text — because the
 * type lives in the cabinet's schema ({@see ContactFieldDefinition}), not in the
 * value.
 */
final readonly class ContactFieldValues
{
    /**
     * @param  array<string, mixed>  $fields  field key => value
     * @param  array<string, FieldOrigin>  $provenance  field key => who wrote it
     */
    public function __construct(
        public array $fields,
        public array $provenance = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fields: Read::map($data, 'fields'),
            provenance: FieldOrigin::mapFrom($data, 'provenance'),
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    public function origin(string $key): ?FieldOrigin
    {
        return $this->provenance[$key] ?? null;
    }

    /** Whether the value was written by your side (an API key), not a person or the AI. */
    public function isFromHost(string $key): bool
    {
        return $this->origin($key)?->source === FieldValueSource::Host;
    }
}
