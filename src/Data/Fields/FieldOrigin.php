<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Fields;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\FieldValueSource;
use Linqelio\Laravel\Data\Read;

/**
 * Who wrote a typed field value, and when (ADR-0061).
 *
 * Values written through this package carry `host`. Provenance is what lets the
 * platform refuse to let a lower-authority writer (`ai`, `platform`) overwrite
 * what a person or your system set.
 */
final readonly class FieldOrigin
{
    public function __construct(
        public ?FieldValueSource $source,
        public ?DateTimeImmutable $at = null,
        public ?string $by = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            source: FieldValueSource::tryFrom(Read::string($data, 'source')),
            at: Read::date($data, 'at'),
            by: Read::stringOrNull($data, 'by'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, self>
     */
    public static function mapFrom(array $data, string $key): array
    {
        $out = [];
        foreach (Read::map($data, $key) as $field => $origin) {
            if (is_array($origin)) {
                $out[$field] = self::fromArray(Read::assoc($origin));
            }
        }

        return $out;
    }
}
