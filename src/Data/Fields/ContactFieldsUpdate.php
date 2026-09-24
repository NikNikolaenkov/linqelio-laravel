<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Fields;

use Linqelio\Laravel\Data\Enums\FieldValueSource;
use Linqelio\Laravel\Data\Read;

/**
 * What a field write did — which is not always what was asked.
 *
 * A value a more authoritative writer set (`human > host > platform > ai`) is
 * NOT overwritten, and that is not an error: it comes back in `kept` with reason
 * `lower_authority`. So a host that writes a phone number an operator already
 * corrected by hand sees its write declined here, rather than silently
 * reverting the correction.
 */
final readonly class ContactFieldsUpdate
{
    /**
     * @param  array<string, mixed>  $fields  every value now on the contact
     * @param  array<string, FieldOrigin>  $provenance
     * @param  array<int, string>  $applied  keys this write set
     * @param  array<int, string>  $cleared  keys this write cleared (sent as null)
     * @param  array<int, KeptField>  $kept  keys this write did NOT change, and why
     */
    public function __construct(
        public array $fields,
        public array $provenance,
        public array $applied,
        public array $cleared,
        public array $kept,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $kept = [];
        foreach (Read::objects($data, 'kept') as $item) {
            $kept[] = new KeptField(
                field: Read::string($item, 'field'),
                reason: Read::string($item, 'reason'),
                held: FieldValueSource::tryFrom(Read::string($item, 'held')),
                offered: FieldValueSource::tryFrom(Read::string($item, 'offered')),
            );
        }

        return new self(
            fields: Read::map($data, 'fields'),
            provenance: FieldOrigin::mapFrom($data, 'provenance'),
            applied: Read::strings($data, 'applied'),
            cleared: Read::strings($data, 'cleared'),
            kept: $kept,
        );
    }

    /** The values as they now stand, without the write report. */
    public function values(): ContactFieldValues
    {
        return new ContactFieldValues($this->fields, $this->provenance);
    }

    /** Everything asked for went through. */
    public function isComplete(): bool
    {
        return $this->kept === [];
    }
}
