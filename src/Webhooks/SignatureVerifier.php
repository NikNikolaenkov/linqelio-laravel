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

    private const PREFIX_V2 = 'v2=';

    public function __construct(private string $secret) {}

    public function verify(string $rawBody, ?string $header): bool
    {
        $header = (string) $header;

        if (! str_starts_with($header, self::PREFIX)) {
            return false;
        }

        return $this->matches($this->sign($rawBody), $header);
    }

    public function sign(string $rawBody): string
    {
        return self::PREFIX.hash_hmac('sha256', $rawBody, $this->secret);
    }

    /**
     * Verifies `X-Linqelio-Signature-V2`, which covers the delivery headers too.
     *
     * `X-Linqelio-Signature` authenticates the body and nothing else, so the two
     * headers that describe the delivery — when it was sent, and which delivery
     * it is — carry no proof of their own. That matters because they are exactly
     * the values worth trusting: the timestamp is stamped per attempt, so it
     * tells one of our retries apart from a captured request being replayed, and
     * the delivery id is what makes a repeat identifiable as the same repeat.
     *
     * v2 binds both into the MAC, over "<timestamp>.<deliveryId>.<body>". A
     * delivery that carries it can be judged on those headers; one that does not
     * has to fall back on the body alone (see VerifySignature).
     */
    public function verifyV2(string $timestamp, string $deliveryId, string $rawBody, ?string $header): bool
    {
        $header = (string) $header;

        if (! str_starts_with($header, self::PREFIX_V2)) {
            return false;
        }

        return $this->matches($this->signV2($timestamp, $deliveryId, $rawBody), $header);
    }

    public function signV2(string $timestamp, string $deliveryId, string $rawBody): string
    {
        return self::PREFIX_V2.hash_hmac('sha256', $timestamp.'.'.$deliveryId.'.'.$rawBody, $this->secret);
    }

    private function matches(string $expected, string $header): bool
    {
        // An empty secret can only produce a MAC of the empty key, which would
        // "verify" anything a misconfigured sender happened to send.
        return $this->secret !== '' && hash_equals($expected, $header);
    }
}
