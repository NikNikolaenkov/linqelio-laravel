<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Analytics;

use DateTimeImmutable;
use Linqelio\Laravel\Data\Read;

/**
 * The days a report covers, in the cabinet's time zone, and the exact moments
 * they begin and end.
 */
final readonly class AnalyticsPeriod
{
    /**
     * @param  string  $from  first day, `YYYY-MM-DD`
     * @param  string  $to  last day, inclusive
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $timeZone = '',
        public ?DateTimeImmutable $start = null,
        public ?DateTimeImmutable $end = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: Read::string($data, 'from'),
            to: Read::string($data, 'to'),
            timeZone: Read::string($data, 'timeZone'),
            start: Read::date($data, 'start'),
            end: Read::date($data, 'end'),
        );
    }
}
