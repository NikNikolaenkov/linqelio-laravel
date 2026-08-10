<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Webhooks;

/**
 * Verifies `X-Linqelio-Signature`: "sha256=" followed by the hex HMAC-SHA256 of
 * the request body, keyed by the secret the endpoint was registered with.
 *
 * Two details matter more than they look:
 *
 *  - the MAC is over the RAW body. Decoding and re-encoding the JSON changes key
 *    order and whitespace, and the signature no longer matches — so this must
 *    see the bytes as they arrived.
 *  - the comparison is constant-time. A regular `===` leaks how much of a forged
 *    signature was right, which is enough to reconstruct one byte at a time.
 */
final readonly class SignatureVerifier
{
    private const PREFIX = 'sha256=';

    public function __construct(private string $secret) {}

    public function verify(string $rawBody, ?string $header): bool
    {
        if ($this->secret === '' || $header === null || $header === '') {
            return false;
        }

        if (! str_starts_with($header, self::PREFIX)) {
            return false;
        }

        return hash_equals($this->sign($rawBody), $header);
    }

    public function sign(string $rawBody): string
    {
        return self::PREFIX.hash_hmac('sha256', $rawBody, $this->secret);
    }
}
