<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * Reads the problem's `errors[]` extension: one entry per offending field.
 *
 * Shared because more than `validation.*` carries it — `contact.field_invalid`
 * names every typed field that failed, and `campaign.invalid` every reason a
 * draft cannot launch.
 *
 * @phpstan-require-extends LinqelioException
 */
trait HasFieldErrors
{
    /**
     * Field name => reason, flattened from the problem's `errors[]`.
     *
     * The contract calls the text `reason`; `message` is read too, for
     * platforms that predate that.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        $errors = $this->problem()['errors'] ?? [];

        if (! is_array($errors)) {
            return [];
        }

        $out = [];
        foreach ($errors as $error) {
            if (! is_array($error) || ! isset($error['field'])) {
                continue;
            }

            $reason = $error['reason'] ?? $error['message'] ?? null;

            if (is_scalar($error['field']) && is_scalar($reason)) {
                $out[(string) $error['field']] = (string) $reason;
            }
        }

        return $out;
    }
}
