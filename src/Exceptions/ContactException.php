<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * A contact-domain refusal: not found, a version conflict, an identity clash —
 * or `contact.field_invalid`, whose {@see HasFieldErrors::errors()} names every
 * typed field that failed (`type_mismatch`, `not_in_enum`, `bad_date`, ...).
 */
class ContactException extends LinqelioException
{
    use HasFieldErrors;
}
