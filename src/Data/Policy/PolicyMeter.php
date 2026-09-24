<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Policy;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * One current limit as a checker sees it — the bars of a pre-send panel.
 *
 * A snapshot, not a reservation: another send can use the room up between the
 * check and yours.
 */
final readonly class PolicyMeter
{
    /**
     * @param  string  $name  stable machine id of the limit, e.g. `rate.burst`
     * @param  DateTimeImmutable|null  $resetAt  when it is whole again; null when
     *                                           it already is, or nobody knows
     */
    public function __construct(
        public string $check,
        public string $name,
        public int $used,
        public int $limit,
        public ?DateTimeImmutable $resetAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            check: Read::string($data, 'check'),
            name: Read::string($data, 'name'),
            used: Read::int($data, 'used'),
            limit: Read::int($data, 'limit'),
            resetAt: Read::date($data, 'resetAt'),
        );
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->used);
    }

    public function isExhausted(): bool
    {
        return $this->limit > 0 && $this->used >= $this->limit;
    }
}
