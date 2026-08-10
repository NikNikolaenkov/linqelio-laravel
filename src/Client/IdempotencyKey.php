<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Client;

use Illuminate\Support\Str;

/**
 * Keys for unsafe commands.
 *
 * The platform treats a repeated key with the same body as the same command and
 * returns the original result; the same key with a different body is a conflict.
 * That makes the key the difference between a retry and a duplicate message
 * arriving on somebody's phone.
 */
final class IdempotencyKey
{
    /** A fresh key for a one-off call. */
    public static function generate(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * A key derived from a queued job's identity.
     *
     * Queue retries re-run the same job instance, so deriving the key from the
     * job's uuid means attempt two carries the key attempt one used. Generating
     * a new key per attempt would turn every transient failure into a duplicate.
     */
    public static function forJob(string $jobUuid): string
    {
        return 'job-'.$jobUuid;
    }

    /**
     * A key owned by the caller's domain.
     *
     * Use when the command has a natural identity — "order 4711 shipped" — so
     * that even a redeploy or a replay from a different process cannot send it
     * twice. Namespaced to keep separate concerns from colliding.
     */
    public static function forSubject(string $namespace, string $id, string $event): string
    {
        return Str::of("{$namespace}-{$id}-{$event}")->slug()->limit(200, '')->value();
    }
}
