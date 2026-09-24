<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Templates;

use Linqelio\Laravel\Data\Read;

/**
 * One placeholder of a template: in the `header` or the `body`, at `index`.
 */
final readonly class TemplateParameter
{
    public function __construct(
        public string $component,
        public int $index,
        public ?string $name = null,
        public ?string $example = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            component: Read::string($data, 'component'),
            index: Read::int($data, 'index'),
            name: Read::stringOrNull($data, 'name'),
            example: Read::stringOrNull($data, 'example'),
        );
    }
}
