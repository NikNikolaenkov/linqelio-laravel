<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What send policy says about a send (ADR-0058 §2, ADR-0082).
 *
 * `allow`: nothing to say. `warn`: sendable, but somebody must be told — and, on
 * a confirmed send, must have acknowledged it. `defer`: not now, retry later.
 * `deny`: not this send.
 */
enum PolicyVerdict: string
{
    case Allow = 'allow';
    case Warn = 'warn';
    case Defer = 'defer';
    case Deny = 'deny';

    /**
     * A verdict newer than this package reads as `deny`.
     *
     * The contract says so, and it is the only safe reading: treating an
     * unknown stance as "go ahead" would send exactly the messages a newer rule
     * exists to stop.
     */
    public static function parse(?string $value): self
    {
        return $value === null ? self::Deny : (self::tryFrom($value) ?? self::Deny);
    }

    /** The send would go through now: `allow`, or `warn` once confirmed. */
    public function isSendable(): bool
    {
        return $this === self::Allow || $this === self::Warn;
    }
}
