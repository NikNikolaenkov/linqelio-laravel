<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * The platform's error registry, in `domain.reason` form.
 *
 * Codes are the stable part of an error response — switch on these rather than
 * on HTTP status or message text. The registry is additive: a code is never
 * reassigned or removed, so matching on one is safe across versions.
 *
 * That also means a newer server can send a code this package has not heard of.
 * {@see self::parse()} maps those to {@see self::Unknown} instead of throwing,
 * so an unrecognised error still surfaces as an error rather than as a crash in
 * the error handler.
 */
enum ErrorCode: string
{
    case ValidationInvalidRequest = 'validation.invalid_request';

    case AuthInvalidKey = 'auth.invalid_key';
    case AuthKeyExpired = 'auth.key_expired';
    case AuthForbiddenScope = 'auth.forbidden_scope';

    case TenancyCabinetNotFound = 'tenancy.cabinet_not_found';
    case TenancyCrossCabinetDenied = 'tenancy.cross_cabinet_denied';

    case KeyringRotationOverlapRequired = 'keyring.rotation_overlap_required';

    case ChannelNotConnected = 'channel.not_connected';
    case ChannelNotFound = 'channel.not_found';
    case ChannelCapabilityUnsupported = 'channel.capability_unsupported';
    case ChannelPairingRequired = 'channel.pairing_required';

    case PolicyRateLimited = 'policy.rate_limited';
    case PolicyQuotaExceeded = 'policy.quota_exceeded';
    case PolicyRuleBlocked = 'policy.rule_blocked';

    case AccessPoolNoNext = 'accesspool.no_next';

    case MessageTypeUnsupported = 'message.type_unsupported';
    case MessageTooLarge = 'message.too_large';

    case IdempotencyKeyReused = 'idempotency.key_reused';

    case ContactNotFound = 'contact.not_found';
    case ContactMergeConflict = 'contact.merge_conflict';
    case ContactIdentityConflict = 'contact.identity_conflict';
    case ContactVersionConflict = 'contact.version_conflict';

    case EmbedTokenExpired = 'embed.token_expired';
    case EmbedScopeViolation = 'embed.scope_violation';
    case EmbedFieldOwnershipDenied = 'embed.field_ownership_denied';

    case ProviderUpstreamError = 'provider.upstream_error';
    case ProviderUnavailable = 'provider.unavailable';

    /** Not in the registry: a code this package predates. */
    case Unknown = 'unknown';

    public static function parse(?string $code): self
    {
        return $code === null ? self::Unknown : (self::tryFrom($code) ?? self::Unknown);
    }

    /** The part before the dot: auth, channel, policy… */
    public function domain(): string
    {
        $domain = strstr($this->value, '.', true);

        return $domain === false ? $this->value : $domain;
    }

    /**
     * Whether repeating the identical request could plausibly succeed.
     *
     * Deliberately narrow. A rate limit or an upstream hiccup passes; a rejected
     * payload or a missing contact does not, and retrying those only burns quota
     * while hiding the real problem.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::PolicyRateLimited,
            self::ProviderUnavailable,
            self::ProviderUpstreamError => true,
            default => false,
        };
    }
}
