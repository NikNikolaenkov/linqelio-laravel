<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Ai;

use Linqelio\Laravel\Data\Read;

/**
 * A questionnaire run, queued.
 *
 * Idempotent per conversation and its newest message: asking again before
 * anything new was said answers the SAME run with `created: false` and spends
 * nothing. Read the outcome later from the profile's `lastRun`.
 */
final readonly class AiProfileFill
{
    public function __construct(
        public string $runId,
        public bool $created,
        public string $conversationId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            runId: Read::string($data, 'runId'),
            created: Read::bool($data, 'created'),
            conversationId: Read::string($data, 'conversationId'),
        );
    }
}
