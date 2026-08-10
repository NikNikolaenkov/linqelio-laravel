<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

use RuntimeException;

/**
 * The package is not configured well enough to make a call.
 *
 * Raised eagerly, with the env var named, rather than letting a request go out
 * against an empty base URL and fail as something unrecognisable further down.
 */
final class ConfigurationException extends RuntimeException
{
    public static function missingBaseUrl(): self
    {
        return new self(
            'Linqelio: LINQELIO_URL is not set. It must point at your installation, '.
            'including the /v1 prefix. There is no default on purpose — a fallback '.
            "would quietly send your customers' messages to another host."
        );
    }

    public static function missingKey(): self
    {
        return new self(
            'Linqelio: LINQELIO_KEY is not set. Issue a client key for the cabinet '.
            '("<cabinetId>.<keyId>.<secret>"); it is shown once at creation and '.
            'stored only as a hash, so a lost key is reissued rather than recovered.'
        );
    }

    public static function missingWebhookSecret(): self
    {
        return new self(
            'Linqelio: LINQELIO_WEBHOOK_SECRET is not set, so delivery signatures '.
            'cannot be verified. Set it to the secret you registered the endpoint '.
            'with, or disable the receiver with LINQELIO_WEBHOOKS_ENABLED=false.'
        );
    }
}
