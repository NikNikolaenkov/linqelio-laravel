<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects a delivery that is not provably ours.
 *
 * Three gates, in order of cost:
 *
 *  1. signature — proves the body came from someone holding the secret;
 *  2. freshness — a delivery older than the tolerance is refused, so a captured
 *     request cannot be replayed indefinitely;
 *  3. single use — the delivery is remembered, so the same capture cannot be
 *     processed twice inside the window where it still looks fresh.
 *
 * The platform retries failed deliveries, and a retry is a legitimate repeat of
 * the same event. That is why the reply to an already-seen delivery is 200 and
 * not an error: the event has been dealt with, and answering otherwise would
 * make the sender keep trying.
 *
 * Gates 2 and 3 need to tell our retry apart from somebody's replay, and how
 * well they can do that depends on what the sender signed:
 *
 *  - with `X-Linqelio-Signature-V2`, the MAC covers the send timestamp and the
 *    delivery id. The timestamp is stamped per attempt, so a retry looks fresh
 *    and a capture ages out — the window can stay tight, and the delivery id is
 *    a single-use key nobody can forge into a fresh one.
 *  - without it, only the body is signed. Age then has to come from the
 *    payload's `occurredAt`, which is fixed at event time and does not reset
 *    between attempts, so the window has to span the platform's entire retry
 *    schedule instead. See RETRY_WINDOW.
 *
 * Both paths are supported: a platform that predates v2 keeps working, and one
 * that sends it gets the stronger guarantee without any configuration.
 */
final class VerifySignature
{
    /**
     * How long the platform keeps retrying one delivery, in seconds.
     *
     * The deliverer makes 6 attempts, backing off 30s and doubling, so the last
     * lands 30+60+120+240+480 = 930s after the event. Rounded up, because two
     * different gates need it:
     *
     *  - it floors the freshness window on the legacy path, where age is
     *    measured from an event-time stamp. Anything lower answers the
     *    platform's own 5th and 6th attempts with a 401 and dead-letters a
     *    delivery we ourselves refused — and rejects any message replayed
     *    through POST /channels/{id}/sync on its first attempt;
     *  - it floors how long a delivery is remembered, on both paths. Under v2 a
     *    retry 930s later is legitimately fresh, so forgetting it sooner would
     *    mean processing the same event twice.
     */
    private const RETRY_WINDOW = 960;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('linqelio.webhooks.secret', '');
        $tolerance = (int) config('linqelio.webhooks.tolerance', 300);

        $verifier = new SignatureVerifier($secret);
        $rawBody = $request->getContent();

        // v1 first, and always: it is what every delivery carries, and failing
        // here means nothing below is worth reading.
        if (! $verifier->verify($rawBody, $request->header('X-Linqelio-Signature'))) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $signatureV2 = (string) $request->header('X-Linqelio-Signature-V2', '');
        $timestamp = (string) $request->header('X-Linqelio-Timestamp', '');
        $deliveryId = (string) $request->header('X-Linqelio-Delivery', '');

        if ($signatureV2 !== '') {
            if (! $verifier->verifyV2($timestamp, $deliveryId, $rawBody, $signatureV2)) {
                return response()->json(['error' => 'invalid signature'], 401);
            }

            $age = $this->ageOf($timestamp);
            $key = 'delivery:'.$deliveryId;
        } else {
            $age = $this->ageOf(is_string($payload['occurredAt'] ?? null) ? $payload['occurredAt'] : '');
            $tolerance = max($tolerance, self::RETRY_WINDOW);
            $key = $this->bodyKey($payload, $rawBody);
        }

        if ($age !== null && $age > $tolerance) {
            return response()->json(['error' => 'delivery too old'], 401);
        }

        if ($this->alreadySeen($key, max($tolerance, self::RETRY_WINDOW))) {
            // Already processed — acknowledge so the platform stops retrying.
            return response()->json(['status' => 'duplicate'], 200);
        }

        return $next($request);
    }

    /**
     * Seconds since `$timestamp`, or null when there is nothing to judge by.
     *
     * Null lets the delivery through: the signature has already proved origin,
     * and dropping a legitimate event over a missing or unparseable stamp is the
     * worse failure. Under v2 the stamp is inside the MAC, so an unusable one
     * means our own sender is misbehaving rather than someone tampering.
     */
    private function ageOf(string $timestamp): ?int
    {
        if ($timestamp === '') {
            return null;
        }

        try {
            return time() - (new \DateTimeImmutable($timestamp))->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }

    private function alreadySeen(string $key, int $ttl): bool
    {
        // add() is atomic: it returns false when the key already existed, which
        // makes this a lock rather than a check-then-set race between workers.
        return ! Cache::add('linqelio:webhook:'.sha1($key), true, $ttl);
    }

    /**
     * What makes two requests the same delivery, when only the body is signed.
     *
     * Deliberately NOT X-Linqelio-Delivery on this path, even though that header
     * is the platform's own identity for the delivery: without v2 the signature
     * covers the body alone, so a replayed capture can carry a fresh value there
     * and walk straight past the single-use gate. Everything used here is inside
     * the MAC.
     *
     * A retry carries byte-identical bytes, so both branches are stable across
     * attempts — which is what makes 200/duplicate the right answer to one.
     *
     * @param  array<string, mixed>  $payload
     */
    private function bodyKey(array $payload, string $rawBody): string
    {
        foreach (['messageId', 'eventId', 'id'] as $field) {
            $value = $payload[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $field.':'.$value;
            }
        }

        // Event types that carry no id of their own still have to be single-use;
        // the body itself distinguishes them, and it is signed.
        return 'body:'.$rawBody;
    }
}
