<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data;

/**
 * What registering a subscription hands back: the subscription, and — only when
 * the platform minted the signing key — that key.
 *
 * A type of its own rather than a nullable field on {@see Webhook}, mirroring the
 * contract's own split. `Webhook` is what every read returns, and it carries no
 * key in any response, ever; a nullable property on it would turn that guarantee
 * into "usually null, so probably safe to log". Here the key is present in the
 * one place it can be, and its type says so.
 *
 * `$secret` is null when you supplied the key yourself, or pointed at one already
 * in the platform's secret store — in both cases you already have it. When it is
 * set, this object is the only place it will ever appear: store it where the
 * receiver reads it from before you discard this, because no read returns it and
 * the way back is deleting the subscription and registering another.
 */
final readonly class RegisteredWebhook
{
    public function __construct(
        public Webhook $subscription,
        public ?string $secret = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $secret = $data['secret'] ?? null;

        return new self(
            subscription: Webhook::fromArray($data),
            secret: is_string($secret) && $secret !== '' ? $secret : null,
        );
    }

    /** The subscription's id, so the common read does not go through `subscription`. */
    public function id(): string
    {
        return $this->subscription->id;
    }

    /**
     * Whether the platform generated the key — that is, whether `$secret` is
     * something you have to save before this object goes out of scope.
     */
    public function mintedSecret(): bool
    {
        return $this->secret !== null;
    }
}
