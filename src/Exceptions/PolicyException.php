<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * Backpressure: rate limit, quota, a policy rule, or an exhausted access pool.
 *
 * These are the errors worth retrying, and the platform usually says when.
 */
class PolicyException extends LinqelioException
{
    /** Seconds to wait before retrying, when the platform said so. */
    public function retryAfter(): ?int
    {
        $value = $this->problem['retryAfter'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
