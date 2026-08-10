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
 *  3. single use — the event id is remembered for that same window, so the same
 *     capture cannot be replayed twice inside it.
 *
 * The platform retries failed deliveries, and a retry is a legitimate repeat of
 * the same event. That is why the reply to an already-seen id is 200 and not an
 * error: the event has been dealt with, and answering otherwise would make the
 * sender keep trying.
 */
final class VerifySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('linqelio.webhooks.secret', '');
        $tolerance = (int) config('linqelio.webhooks.tolerance', 300);

        $verifier = new SignatureVerifier($secret);

        if (! $verifier->verify($request->getContent(), $request->header('X-Linqelio-Signature'))) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();

        if ($this->isStale($payload, $tolerance)) {
            return response()->json(['error' => 'delivery too old'], 401);
        }

        if ($this->alreadySeen($payload, $tolerance)) {
            // Already processed — acknowledge so the platform stops retrying.
            return response()->json(['status' => 'duplicate'], 200);
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isStale(array $payload, int $tolerance): bool
    {
        $occurredAt = $payload['occurredAt'] ?? null;

        if (! is_string($occurredAt) || $occurredAt === '') {
            // No timestamp to judge by. The signature already proved origin, so
            // let it through rather than dropping a legitimate event.
            return false;
        }

        try {
            $age = time() - (new \DateTimeImmutable($occurredAt))->getTimestamp();
        } catch (\Exception) {
            return false;
        }

        return $age > $tolerance;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function alreadySeen(array $payload, int $tolerance): bool
    {
        $id = $payload['messageId'] ?? $payload['eventId'] ?? null;

        if (! is_string($id) || $id === '') {
            return false;
        }

        // add() is atomic: it returns false when the key already existed, which
        // makes this a lock rather than a check-then-set race between workers.
        return ! Cache::add('linqelio:webhook:'.sha1($id), true, $tolerance);
    }
}
