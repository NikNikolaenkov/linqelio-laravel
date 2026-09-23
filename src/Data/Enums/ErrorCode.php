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
    case AuthInvalidCredentials = 'auth.invalid_credentials';
    case AuthAccountLocked = 'auth.account_locked';
    case AuthSessionInvalid = 'auth.session_invalid';
    case AuthOriginRejected = 'auth.origin_rejected';
    case AuthTokenInvalid = 'auth.token_invalid';
    case AuthSecondFactorRequired = 'auth.second_factor_required';
    case AuthSecondFactorInvalid = 'auth.second_factor_invalid';
    case AuthSecondFactorConflict = 'auth.second_factor_conflict';

    case TenancyCabinetNotFound = 'tenancy.cabinet_not_found';
    case TenancyCrossCabinetDenied = 'tenancy.cross_cabinet_denied';
    case TenancyQuotaExceeded = 'tenancy.quota_exceeded';
    case TenancyOrganizationNotFound = 'tenancy.organization_not_found';
    case TenancySuspended = 'tenancy.suspended';

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
    case MessageNotFound = 'message.not_found';

    case ConversationNotFound = 'conversation.not_found';
    case WebhookNotFound = 'webhook.not_found';
    case AccessPoolNotFound = 'accesspool.not_found';
    case DeadLetterNotFound = 'deadletter.not_found';

    case IdempotencyKeyReused = 'idempotency.key_reused';

    case ContactNotFound = 'contact.not_found';
    case ContactMergeConflict = 'contact.merge_conflict';
    case ContactIdentityConflict = 'contact.identity_conflict';
    case ContactVersionConflict = 'contact.version_conflict';
    /** A typed contact field failed validation; the problem carries errors[] per field. */
    case ContactFieldInvalid = 'contact.field_invalid';

    /**
     * Membership integrity (409). The change would leave a cabinet or an
     * organisation without an administrator, or would let someone lower their
     * own role — an escalation path with nobody else in the loop.
     */
    case TeamLastOrgOwner = 'team.last_org_owner';
    case TeamLastCabinetAdmin = 'team.last_cabinet_admin';
    case TeamSelfDemotionDenied = 'team.self_demotion_denied';
    case TeamRoleCeilingExceeded = 'team.role_ceiling_exceeded';
    case TeamVersionConflict = 'team.version_conflict';
    case TeamAccountExists = 'team.account_exists';
    case TeamMemberNotFound = 'team.member_not_found';
    case TeamInviteNotFound = 'team.invite_not_found';
    case TeamInviteWrongAccount = 'team.invite_wrong_account';
    case TeamAlreadyMember = 'team.already_member';

    case PlatformLastOperator = 'platform.last_operator';
    case PlatformAccountNotFound = 'platform.account_not_found';
    case PlatformOperatorNotFound = 'platform.operator_not_found';

    /** A settings section was written from a stale version (412). */
    case SettingsVersionConflict = 'settings.version_conflict';

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
