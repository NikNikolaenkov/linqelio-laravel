<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * The request was rejected before it did anything.
 *
 * RFC 9457 allows extension members; the platform uses `errors[]` to say which
 * fields failed and why, so the caller can map them onto a form rather than
 * showing a generic "invalid request".
 */
class ValidationException extends LinqelioException
{
    /**
     * Field name => message, flattened from the problem's `errors[]`.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        $errors = $this->problem['errors'] ?? [];

        if (! is_array($errors)) {
            return [];
        }

        $out = [];
        foreach ($errors as $error) {
            if (is_array($error) && isset($error['field'], $error['message'])) {
                $out[(string) $error['field']] = (string) $error['message'];
            }
        }

        return $out;
    }
}
