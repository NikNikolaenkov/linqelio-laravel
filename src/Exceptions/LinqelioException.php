<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

use Linqelio\Laravel\Data\Enums\ErrorCode;
use RuntimeException;

/**
 * Base for every error the API reports.
 *
 * Responses follow RFC 9457 (`application/problem+json`) and carry a stable
 * `code`. Subclasses exist per domain so a caller can catch the family it cares
 * about — `catch (PolicyException)` for backpressure, say — without enumerating
 * individual codes.
 *
 * Catching this class alone is enough to distinguish "the API said no" from a
 * transport failure, which surfaces as ConnectionException from the HTTP client.
 */
class LinqelioException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $problem  the decoded problem+json body
     */
    public function __construct(
        string $message,
        protected readonly ErrorCode $code_,
        protected readonly int $status,
        protected readonly array $problem = [],
        protected readonly ?string $requestId = null,
    ) {
        parent::__construct($message, $status);
    }

    public function errorCode(): ErrorCode
    {
        return $this->code_;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * The raw problem+json body, for anything the typed accessors omit.
     *
     * @return array<string, mixed>
     */
    public function problem(): array
    {
        return $this->problem;
    }

    /** Correlates this failure with the platform's own logs. */
    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function isRetryable(): bool
    {
        return $this->code_->isRetryable();
    }
}
