<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * A link from a platform contact back to your own record.
 *
 * The platform never decides that its contact and your customer are the same
 * person — that judgement is yours, and this is where you record it. `system` is
 * whatever you call your side ("crm", "billing"), `externalId` the id in it.
 *
 * A contact can carry several: the same person may be a customer in one of your
 * systems and a supplier in another, and collapsing that into one field would
 * force you to pick which truth to keep.
 */
final readonly class HostRef
{
    public function __construct(
        public string $system,
        public string $externalId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            system: (string) ($data['system'] ?? ''),
            externalId: (string) ($data['externalId'] ?? ''),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['system' => $this->system, 'externalId' => $this->externalId];
    }
}
