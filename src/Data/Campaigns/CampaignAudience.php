<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Campaigns;

use Linqelio\Laravel\Data\Read;

/**
 * Who a campaign is for — a DEFINITION, resolved into recipients once, at launch.
 *
 * Recipients are `contactIds` plus the contacts matching the filter (when the
 * filter has a member): carrying every tag, whose typed fields equal the given
 * text, of the given status — or everyone, with `all` — and reachable on one of
 * the campaign's channels. Each person once; merged-away contacts never.
 *
 * `all` has to be asked for by name ({@see self::everyone()}). An empty
 * definition is not "everybody", it is nobody.
 */
final readonly class CampaignAudience
{
    /**
     * @param  array<int, string>  $contactIds  explicit recipients (up to 10 000)
     * @param  array<int, string>  $tags  a contact must carry EVERY one (up to 20)
     * @param  array<string, string>  $fields  typed field key => the value its text must equal
     * @param  string|null  $status  `new` or `active`; null = both
     */
    public function __construct(
        public array $contactIds = [],
        public array $tags = [],
        public array $fields = [],
        public ?string $status = null,
        public bool $all = false,
    ) {}

    /**
     * @param  array<int, string>  $contactIds
     */
    public static function contacts(array $contactIds): self
    {
        return new self(contactIds: array_values($contactIds));
    }

    /**
     * @param  array<int, string>  $tags
     */
    public static function tagged(array $tags): self
    {
        return new self(tags: array_values($tags));
    }

    /** Every contact reachable on the campaign's channels. */
    public static function everyone(): self
    {
        return new self(all: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contactIds: Read::strings($data, 'contactIds'),
            tags: Read::strings($data, 'tags'),
            fields: Read::stringMap($data, 'fields'),
            status: Read::stringOrNull($data, 'status'),
            all: Read::bool($data, 'all'),
        );
    }

    /**
     * Only the members that say something: an empty list is left out rather than
     * sent as a filter that matches nobody.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Read::compact([
            'contactIds' => $this->contactIds === [] ? null : array_values($this->contactIds),
            'tags' => $this->tags === [] ? null : array_values($this->tags),
            'fields' => $this->fields === [] ? null : $this->fields,
            'status' => $this->status,
            'all' => $this->all ? true : null,
        ]);
    }
}
