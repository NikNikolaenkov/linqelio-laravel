<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Alerts;

use Linqelio\Laravel\Data\Read;

/**
 * The answer to an acknowledge or a resolve.
 *
 * Both are idempotent: acting on an alert already in that state (or further
 * along) changes nothing and answers `changed: false` — a success, so a retry
 * after a lost response is not an error.
 */
final readonly class AlertChange
{
    public function __construct(
        public Alert $alert,
        public bool $changed,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            alert: Alert::fromArray(Read::map($data, 'alert')),
            changed: Read::bool($data, 'changed'),
        );
    }
}
