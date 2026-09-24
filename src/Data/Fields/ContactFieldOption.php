<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Fields;

/**
 * One choice of an `enum` / `multi_enum` field: the stable `value` stored on the
 * contact, and its label per locale.
 */
final readonly class ContactFieldOption
{
    /**
     * @param  array<string, string>  $label
     */
    public function __construct(
        public string $value,
        public array $label = [],
    ) {}
}
