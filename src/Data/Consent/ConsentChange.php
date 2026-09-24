<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Consent;

use Linqelio\Laravel\Data\Read;

/**
 * The answer to a grant or a revoke: the consent as it now stands, and whether
 * this call changed it.
 *
 * `changed: false` is a success, not a refusal. Both calls are idempotent — an
 * active consent is left alone (the first grant's provenance is the earliest
 * evidence and stands), a revoked one stays revoked from its first moment — so
 * a retry after a lost response lands here.
 */
final readonly class ConsentChange
{
    public function __construct(
        public bool $changed,
        public ContactConsent $consent,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            changed: Read::bool($data, 'changed'),
            consent: ContactConsent::fromArray(Read::object($data, 'consent') ?? []),
        );
    }
}
