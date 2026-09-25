<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

use Linqelio\Laravel\Data\Enums\ErrorCode;

/**
 * The Idempotency-Key did not match what the platform remembers under it
 * (ADR-0103). Both codes answer 409, and nothing was done by this request.
 *
 *  - `idempotency.key_reused` — the key belongs to a DIFFERENT request (another
 *    body, path or query). A bug on the caller's side: two commands share a
 *    key. Retrying will not help; derive keys from what makes a command unique.
 *  - `idempotency.in_progress` — the SAME request is still running under this
 *    key. Wait {@see self::retryAfter()} seconds and send it again, unchanged:
 *    once the first one finishes, the retry gets its stored answer replayed.
 */
class IdempotencyException extends LinqelioException
{
    /** The first request with this key is still running; retry it unchanged. */
    public function isInProgress(): bool
    {
        return $this->code_ === ErrorCode::IdempotencyInProgress;
    }

    /** The key was already used for a different request. Retrying cannot help. */
    public function isKeyReused(): bool
    {
        return $this->code_ === ErrorCode::IdempotencyKeyReused;
    }
}
