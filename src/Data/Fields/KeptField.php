<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Fields;

use Linqelio\Laravel\Data\Enums\FieldValueSource;

/**
 * A field a write left alone: `held` is who wrote the value that stands,
 * `offered` who tried to replace it.
 */
final readonly class KeptField
{
    /**
     * @param  string  $reason  machine reason, e.g. `lower_authority`
     */
    public function __construct(
        public string $field,
        public string $reason,
        public ?FieldValueSource $held = null,
        public ?FieldValueSource $offered = null,
    ) {}
}
