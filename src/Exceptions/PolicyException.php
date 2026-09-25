<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

use Linqelio\Laravel\Data\Enums\ErrorCode;
use Linqelio\Laravel\Data\Policy\PolicyFinding;

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

        // The problem's own member first (it is the finding's figure); the
        // Retry-After header otherwise.
        return is_numeric($value) ? (int) $value : parent::retryAfter();
    }

    /**
     * The send-policy findings behind the refusal (the problem's
     * `policyFindings`).
     *
     * With `policy.confirmation_required` these are the warnings that appeared
     * after the check and were NOT acknowledged — nothing was sent. Show them,
     * and send again with their keys acknowledged. With a rate limit, quota or
     * rule block they are every finding of the run that held the send.
     *
     * @return array<int, PolicyFinding>
     */
    public function findings(): array
    {
        return PolicyFinding::listFrom($this->problem, 'policyFindings');
    }

    /** A confirmed send met a warning it had not acknowledged. */
    public function needsConfirmation(): bool
    {
        return $this->code_ === ErrorCode::PolicyConfirmationRequired;
    }

    /**
     * The keys to add to `acknowledgedWarnings` once the new warnings are
     * confirmed.
     *
     * @return array<int, string>
     */
    public function unacknowledgedWarnings(): array
    {
        $keys = [];
        foreach ($this->findings() as $finding) {
            if ($finding->isWarning()) {
                $keys[] = $finding->key;
            }
        }

        return $keys;
    }
}
