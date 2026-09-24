<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Health;

use Linqelio\Laravel\Data\Read;

/**
 * One ingredient of a health score and what it cost.
 */
final readonly class HealthComponent
{
    /**
     * @param  float  $lost  score points this component took off
     * @param  bool  $insufficientData  too little traffic to judge it
     * @param  array<string, float>  $params  the numbers behind `reason`
     */
    public function __construct(
        public string $key,
        public int $weight = 0,
        public int $score = 0,
        public float $lost = 0.0,
        public string $reason = '',
        public bool $insufficientData = false,
        public array $params = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Read::string($data, 'key'),
            weight: Read::int($data, 'weight'),
            score: Read::int($data, 'score'),
            lost: Read::float($data, 'lost'),
            reason: Read::string($data, 'reason'),
            insufficientData: Read::bool($data, 'insufficientData'),
            params: Read::floatMap($data, 'params'),
        );
    }
}
