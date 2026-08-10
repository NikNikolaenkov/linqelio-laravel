<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

use Linqelio\Laravel\Data\Enums\ErrorCode;

/**
 * Turns a problem+json response into the most specific exception available.
 *
 * Selection is by the code's DOMAIN, not by an exhaustive match on every code.
 * The registry is additive, so a new `channel.something_new` should still arrive
 * as a ChannelException rather than degrading to the base class the moment the
 * server adds a code.
 */
final class ExceptionFactory
{
    /**
     * @param  array<string, mixed>  $problem
     */
    public static function make(array $problem, int $status, ?string $requestId = null): LinqelioException
    {
        $raw = is_string($problem['code'] ?? null) ? $problem['code'] : null;
        $code = ErrorCode::parse($raw);
        $message = self::message($problem, $code, $status);

        // The domain comes from the RAW code, not from the parsed enum. An
        // unrecognised code parses to Unknown, and reading the domain off that
        // would send every future `channel.*` to the base class — exactly the
        // degradation the additive registry is supposed to avoid.
        return match (self::domainOf($raw)) {
            'validation' => new ValidationException($message, $code, $status, $problem, $requestId),
            'auth' => new AuthException($message, $code, $status, $problem, $requestId),
            'tenancy', 'keyring' => new TenancyException($message, $code, $status, $problem, $requestId),
            'channel' => new ChannelException($message, $code, $status, $problem, $requestId),
            'policy', 'accesspool' => new PolicyException($message, $code, $status, $problem, $requestId),
            'message' => new MessageException($message, $code, $status, $problem, $requestId),
            'idempotency' => new IdempotencyException($message, $code, $status, $problem, $requestId),
            'contact' => new ContactException($message, $code, $status, $problem, $requestId),
            'embed' => new EmbedException($message, $code, $status, $problem, $requestId),
            'provider' => new ProviderException($message, $code, $status, $problem, $requestId),
            default => new LinqelioException($message, $code, $status, $problem, $requestId),
        };
    }

    /** The part before the dot of whatever the server actually sent. */
    private static function domainOf(?string $raw): string
    {
        if ($raw === null || ! str_contains($raw, '.')) {
            return '';
        }

        return strstr($raw, '.', true) ?: '';
    }

    /**
     * RFC 9457 splits the human-readable part in two: `title` names the problem
     * type, `detail` describes this occurrence. Prefer the specific one, and
     * fall back to something that still identifies the failure when a proxy or
     * a gateway returns a non-problem body.
     *
     * @param  array<string, mixed>  $problem
     */
    private static function message(array $problem, ErrorCode $code, int $status): string
    {
        foreach (['detail', 'title'] as $key) {
            if (is_string($problem[$key] ?? null) && $problem[$key] !== '') {
                return $problem[$key];
            }
        }

        return $code === ErrorCode::Unknown
            ? "Linqelio request failed with HTTP {$status}"
            : "Linqelio request failed: {$code->value}";
    }
}
