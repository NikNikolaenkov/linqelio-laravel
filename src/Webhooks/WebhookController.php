<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Linqelio\Laravel\Events\WebhookReceived;

/**
 * Receives a delivery and gets out of the way.
 *
 * Everything meaningful happens on a queue. Reading a message body means calling
 * the API, and doing that inside the webhook request would hold the platform's
 * delivery open for the round trip — turning a slow read on our side into a
 * timeout and a retry on theirs.
 *
 * Signature, freshness and replay have already been settled by the middleware,
 * so reaching this point means the delivery is genuine and new.
 */
final class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $job = ProcessWebhook::dispatch($payload);

        /** @var array{connection: ?string, queue: ?string} $queue */
        $queue = config('linqelio.webhooks.queue', []);

        if (($queue['connection'] ?? null) !== null) {
            $job->onConnection($queue['connection']);
        }

        if (($queue['queue'] ?? null) !== null) {
            $job->onQueue($queue['queue']);
        }

        // Raw event, for anything the typed events do not cover yet — the
        // registry is additive, so a listener can pick up a new event type
        // without waiting for this package to catch up.
        WebhookReceived::dispatch($payload);

        return new JsonResponse(['status' => 'accepted'], 202);
    }
}
