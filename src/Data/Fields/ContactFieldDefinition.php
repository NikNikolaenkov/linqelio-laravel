<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Fields;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Enums\ContactFieldOwner;
use Linqelio\Laravel\Data\Enums\ContactFieldType;
use Linqelio\Laravel\Data\Read;

/**
 * One typed field of the cabinet's contact schema.
 *
 * Read-only from here: the schema is edited in the console by somebody with
 * settings:manage. A host reads it to know what it may write and in what shape.
 */
final readonly class ContactFieldDefinition
{
    /**
     * @param  array<string, string>  $label  locale => label
     * @param  array<int, ContactFieldOption>  $options  the choices of an `enum` / `multi_enum` field
     * @param  bool  $aiExtract  the AI questionnaire fills this field
     * @param  bool  $syncToDesk  written values are projected into the operator desk
     */
    public function __construct(
        public string $key,
        public ?ContactFieldType $type,
        public array $label = [],
        public array $options = [],
        public ?ContactFieldOwner $owner = null,
        public bool $aiExtract = false,
        public string $aiHint = '',
        public bool $syncToDesk = false,
        public int $position = 0,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $options = [];
        foreach (Read::objects($data, 'options') as $option) {
            $options[] = new ContactFieldOption(
                value: Read::string($option, 'value'),
                label: Read::stringMap($option, 'label'),
            );
        }

        return new self(
            key: Read::string($data, 'key'),
            type: ContactFieldType::tryFrom(Read::string($data, 'type')),
            label: Read::stringMap($data, 'label'),
            options: $options,
            owner: ContactFieldOwner::tryFrom(Read::string($data, 'owner')),
            aiExtract: Read::bool($data, 'aiExtract'),
            aiHint: Read::string($data, 'aiHint'),
            syncToDesk: Read::bool($data, 'syncToDesk'),
            position: Read::int($data, 'position'),
            createdAt: Read::date($data, 'createdAt'),
            updatedAt: Read::date($data, 'updatedAt'),
        );
    }

    /** The label in one locale, falling back to any label, then to the key. */
    public function labelIn(string $locale): string
    {
        return $this->label[$locale] ?? (array_values($this->label)[0] ?? $this->key);
    }

    /**
     * The stable machine values an `enum` / `multi_enum` field accepts.
     *
     * @return array<int, string>
     */
    public function allowedValues(): array
    {
        return array_map(static fn (ContactFieldOption $o): string => $o->value, $this->options);
    }
}
