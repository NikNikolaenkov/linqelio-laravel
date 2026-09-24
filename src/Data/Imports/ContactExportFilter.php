<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Imports;

use Linqelio\Laravel\Data\Enums\ContactStatus;
use Linqelio\Laravel\Data\Read;

/**
 * Which contacts an export takes. Every member narrows; an empty filter exports
 * every contact the key may see.
 */
final readonly class ContactExportFilter
{
    /**
     * @param  array<int, string>  $tags  a contact must carry every one
     * @param  array<int, string>  $channelIds  reachable on one of these
     * @param  array<string, string>  $fields  typed field key => the value its text must equal
     */
    public function __construct(
        public array $tags = [],
        public array $channelIds = [],
        public array $fields = [],
        public ?ContactStatus $status = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tags: Read::strings($data, 'tags'),
            channelIds: Read::strings($data, 'channelIds'),
            fields: Read::stringMap($data, 'fields'),
            status: ContactStatus::tryFrom(Read::string($data, 'status')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Read::compact([
            'tags' => $this->tags === [] ? null : array_values($this->tags),
            'channelIds' => $this->channelIds === [] ? null : array_values($this->channelIds),
            'fields' => $this->fields === [] ? null : $this->fields,
            'status' => $this->status?->value,
        ]);
    }
}
