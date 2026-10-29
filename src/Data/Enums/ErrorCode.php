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
    /** The email is not confirmed yet (403). */
    case AuthEmailUnverified = 'auth.email_unverified';
    /** An account with that email already exists (409); log in instead of signing up. */
    case AuthAccountExists = 'auth.account_exists';

    case TenancyCabinetNotFound = 'tenancy.cabinet_not_found';
    case TenancyCrossCabinetDenied = 'tenancy.cross_cabinet_denied';
    case TenancyQuotaExceeded = 'tenancy.quota_exceeded';
    case TenancyOrganizationNotFound = 'tenancy.organization_not_found';
    case TenancySuspended = 'tenancy.suspended';
    /** The cabinet still has channels or a connected desk and cannot be deleted (409), ADR-0126. */
    case TenancyCabinetNotEmpty = 'tenancy.cabinet_not_empty';

    case KeyringRotationOverlapRequired = 'keyring.rotation_overlap_required';

    case ChannelNotConnected = 'channel.not_connected';
    case ChannelNotFound = 'channel.not_found';
    case ChannelCapabilityUnsupported = 'channel.capability_unsupported';
    case ChannelPairingRequired = 'channel.pairing_required';
    /** The connected account differs from the expected phone (409), ADR-0124. */
    case ChannelAccountMismatch = 'channel.account_mismatch';
    /** Another channel of the cabinet already holds that number (409), ADR-0124. */
    case ChannelAccountInUse = 'channel.account_in_use';

    case PolicyRateLimited = 'policy.rate_limited';
    case PolicyQuotaExceeded = 'policy.quota_exceeded';
    case PolicyRuleBlocked = 'policy.rule_blocked';
    case PolicyConfirmationRequired = 'policy.confirmation_required';
    case PolicyConsentMissing = 'policy.consent_missing';
    case PolicyDailyLimit = 'policy.daily_limit';
    case PolicyHourlyLimit = 'policy.hourly_limit';
    case PolicyContactFrequency = 'policy.contact_frequency';
    case PolicyDuplicateContent = 'policy.duplicate_content';
    case PolicyOutsideBusinessHours = 'policy.outside_business_hours';
    case PolicyDialogFrozen = 'policy.dialog_frozen';
    case PolicyMonthlyQuotaNear = 'policy.monthly_quota_near';

    case ContactMergeProposalNotFound = 'contact.merge_proposal_not_found';
    case ContactMergeNotFound = 'contact.merge_not_found';
    case ContactMergeUndoUnsafe = 'contact.merge_undo_unsafe';
    case ContactMergeUndoExpired = 'contact.merge_undo_expired';
    case ContactImportInvalid = 'contact.import_invalid';
    case ContactImportTooLarge = 'contact.import_too_large';
    case ContactJobNotFound = 'contact.job_not_found';
    case ContactJobConflict = 'contact.job_conflict';
    case ContactExportExpired = 'contact.export_expired';
    case RealtimeTooManyStreams = 'realtime.too_many_streams';

    case BitrixPairingInvalid = 'bitrix.pairing_invalid';
    case BitrixAuthInvalid = 'bitrix.auth_invalid';
    case BitrixPortalLinkedElsewhere = 'bitrix.portal_linked_elsewhere';
    case BitrixCabinetHasPortal = 'bitrix.cabinet_has_portal';
    case BitrixPortalNotLinked = 'bitrix.portal_not_linked';
    case BitrixUserNotLinked = 'bitrix.user_not_linked';
    case BitrixPortalNotFound = 'bitrix.portal_not_found';
    case BitrixLinkCodeInvalid = 'bitrix.link_code_invalid';
    case CreatioInstallationNotFound = 'creatio.installation_not_found';
    case CreatioInstallationExists = 'creatio.installation_exists';
    case CreatioAuthInvalid = 'creatio.auth_invalid';
    case CreatioUserNotLinked = 'creatio.user_not_linked';
    case CreatioStateInvalid = 'creatio.state_invalid';
    case OauthTokenInvalid = 'oauth.token_invalid';
    case OauthRequestInvalid = 'oauth.request_invalid';
    case OauthGrantNotFound = 'oauth.grant_not_found';
    case PolicyServiceWindowClosed = 'policy.service_window_closed';
    case TemplateNotFound = 'template.not_found';
    case TemplateNotApproved = 'template.not_approved';
    case TemplateUnsupported = 'template.unsupported';
    case TemplateParamsInvalid = 'template.params_invalid';
    case TemplateSyncNotConfigured = 'template.sync_not_configured';
    case PolicyGroupsDisabled = 'policy.groups_disabled';
    case AiNotConfigured = 'ai.not_configured';
    case AiProviderUnsupported = 'ai.provider_unsupported';
    case CampaignNotFound = 'campaign.not_found';
    case CampaignStateConflict = 'campaign.state_conflict';
    case CampaignInvalid = 'campaign.invalid';
    case ScheduledSendNotFound = 'scheduled_send.not_found';
    case ScheduledSendStateConflict = 'scheduled_send.state_conflict';
    case AlertNotFound = 'alert.not_found';
    case AlertSubscriptionNotFound = 'alert.subscription_not_found';
    case AlertSubscriptionConflict = 'alert.subscription_conflict';
    case AlertSubscriptionInvalid = 'alert.subscription_invalid';
    case AlertRuleNotFound = 'alert.rule_not_found';
    case AlertRuleInvalid = 'alert.rule_invalid';

    case AccessPoolNoNext = 'accesspool.no_next';

    case MessageTypeUnsupported = 'message.type_unsupported';
    case MessageTooLarge = 'message.too_large';
    case MessageNotFound = 'message.not_found';

    /** The media id is unknown or belongs to another cabinet (404), ADR-0126. */
    case MediaNotFound = 'media.not_found';
    /** The stored file failed its integrity check (500); retrying does not help, ADR-0126. */
    case MediaUnreadable = 'media.unreadable';

    case ConversationNotFound = 'conversation.not_found';
    case ThreadNotFound = 'thread.not_found';
    case WebhookNotFound = 'webhook.not_found';
    case AccessPoolNotFound = 'accesspool.not_found';
    case DeadLetterNotFound = 'deadletter.not_found';

    case IdempotencyKeyReused = 'idempotency.key_reused';
    /** The first request with this key is still running (409 + Retry-After); retry it unchanged. */
    case IdempotencyInProgress = 'idempotency.in_progress';

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
    /** A platform operator reached cabinet data without a support access window (403), ADR-0125. */
    case PlatformSupportAccessRequired = 'platform.support_access_required';
    /** The organization has had members; the platform no longer acts in it (409), ADR-0125. */
    case PlatformOrganizationHasMembers = 'platform.organization_has_members';
    /** A platform operator cannot be an organization's first owner (422), ADR-0125. */
    case PlatformOwnerIsOperator = 'platform.owner_is_operator';

    case SupportGrantNotFound = 'support.grant_not_found';
    /** An operator tried to confirm their own emergency access request (409), ADR-0125. */
    case SupportEmergencySelfApproval = 'support.emergency_self_approval';
    /** The emergency access request is no longer waiting for confirmation (409), ADR-0125. */
    case SupportEmergencyNotPending = 'support.emergency_not_pending';
    /** The client cannot withdraw emergency access; it ends on its own (409), ADR-0125. */
    case SupportEmergencyNotRevocable = 'support.emergency_not_revocable';

    /** A settings section was written from a stale version (412). */
    case SettingsVersionConflict = 'settings.version_conflict';

    case EmbedTokenExpired = 'embed.token_expired';
    case EmbedScopeViolation = 'embed.scope_violation';
    case EmbedFieldOwnershipDenied = 'embed.field_ownership_denied';

    case ProviderUpstreamError = 'provider.upstream_error';
    case ProviderUnavailable = 'provider.unavailable';

    // Round 11: groups, AI guard and questionnaire, compliance, analytics, campaign dry run, Bitrix24 connector.
    case ConversationNotGroup = 'conversation.not_group';
    case GroupMembersUnresolvable = 'group.members_unresolvable';
    case AiGuardRisk = 'ai.guard_risk';
    case AiGuardUnavailable = 'ai.guard_unavailable';
    case AiDisabled = 'ai.disabled';
    case AiProfileNoConversation = 'ai.profile_no_conversation';
    case ComplianceScanNotFound = 'compliance.scan_not_found';
    case ComplianceFindingNotFound = 'compliance.finding_not_found';
    case ComplianceScanActive = 'compliance.scan_active';
    case ComplianceScanFinished = 'compliance.scan_finished';
    case ComplianceAiDisabled = 'compliance.ai_disabled';
    case ComplianceNoRules = 'compliance.no_rules';
    case ComplianceAssigneeInvalid = 'compliance.assignee_invalid';
    case AnalyticsRangeInvalid = 'analytics.range_invalid';
    case AnalyticsExportNotFound = 'analytics.export_not_found';
    case AnalyticsExportNotReady = 'analytics.export_not_ready';
    case AnalyticsExportExpired = 'analytics.export_expired';
    case CampaignDryRunRequired = 'campaign.dry_run_required';
    case BitrixPortalNotLive = 'bitrix.portal_not_live';

    // Round 12: Bitrix24 CRM, the CRM card widget, the contact centre.
    case BitrixCrmMappingInvalid = 'bitrix.crm_mapping_invalid';
    case BitrixCrmEntityUnavailable = 'bitrix.crm_entity_unavailable';
    case EmbedConversationUnsupported = 'embed.conversation_unsupported';

    // Round 13: creating a contact from a Bitrix24 CRM card.
    case BitrixCrmContactUnaddressable = 'bitrix.crm_contact_unaddressable';
    case BitrixCrmLinkedElsewhere = 'bitrix.crm_linked_elsewhere';

    // Meta channels: connecting Facebook Pages and Instagram accounts by logging in.
    /** The platform has no Meta app configured yet (409); an operator must set it up. */
    case MetaAppNotConfigured = 'meta.app_not_configured';
    /** The login result is unknown, expired, used or belongs to someone else (404). */
    case MetaGrantInvalid = 'meta.grant_invalid';
    /** That Page or Instagram account is already a channel of another cabinet. */
    case MetaAccountConnectedElsewhere = 'meta.account_connected_elsewhere';
    /** The comment action cannot be done on this comment (422): deleted, your own, a mention, or not allowed by Meta. */
    case MetaCommentActionUnavailable = 'meta.comment_action_unavailable';
    /** The one private reply to this comment was already sent, or its 7 days have passed (422). */
    case MetaPrivateReplyUnavailable = 'meta.private_reply_unavailable';

    // Cabinet API keys managed by the cabinet itself.
    /** No such key in this cabinet (404), or it was already revoked. */
    case KeyringKeyNotFound = 'keyring.key_not_found';
    /** The key would get a scope its issuer does not hold, or one never grantable here (403). */
    case KeyringScopeNotGrantable = 'keyring.scope_not_grantable';

    // Apps that connect to an organization (Linqelio #229).
    /** The organization is already connected to this app (409). */
    case AppsAlreadyConnected = 'apps.already_connected';
    /** No app with that id in the registry (404). */
    case AppsAppNotFound = 'apps.app_not_found';
    /** The app id is taken, or the app has connections and cannot be deleted (409). */
    case AppsAppInUse = 'apps.app_in_use';
    /** No live connection with that id in the organization (404). */
    case AppsConnectionNotFound = 'apps.connection_not_found';

    // The platform's own mail settings (Linqelio ADR-0123). The platform operator
    // sends the test mail; all but mail.not_configured come back as the
    // `errorCode` of a test result, not as an error response.
    /** The platform mails to the log only; there is nothing to test (409). */
    case MailNotConfigured = 'mail.not_configured';
    /** The mail server refused the login or password. */
    case MailAuth = 'mail.auth';
    /** The mail server could not be reached. */
    case MailConnect = 'mail.connect';
    /** The encrypted connection to the mail server failed. */
    case MailTls = 'mail.tls';
    /** The mail server refused the sender, the recipient or the message. */
    case MailRejected = 'mail.rejected';
    /** The mail server did not answer in time. */
    case MailTimeout = 'mail.timeout';
    /** The mail server password could not be read from the secret store. */
    case MailSecretUnavailable = 'mail.secret_unavailable';

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
            self::IdempotencyInProgress,
            self::ProviderUnavailable,
            self::ProviderUpstreamError => true,
            default => false,
        };
    }
}
