<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Analytics\AnalyticsExportRequest;
use Linqelio\Laravel\Data\Campaigns\CampaignAudience;
use Linqelio\Laravel\Data\Campaigns\CampaignContent;
use Linqelio\Laravel\Data\Campaigns\CampaignInput;
use Linqelio\Laravel\Data\Campaigns\CampaignTemplate;
use Linqelio\Laravel\Data\Campaigns\CampaignTemplateParam;
use Linqelio\Laravel\Data\Campaigns\CampaignWindow;
use Linqelio\Laravel\Data\Enums\AlertDeliveryChannel;
use Linqelio\Laravel\Data\Enums\AlertSeverity;
use Linqelio\Laravel\Data\Enums\AlertState;
use Linqelio\Laravel\Data\Enums\AlertStatus;
use Linqelio\Laravel\Data\Enums\AlertType;
use Linqelio\Laravel\Data\Enums\AnalyticsBreakdownBy;
use Linqelio\Laravel\Data\Enums\AnalyticsExportFormat;
use Linqelio\Laravel\Data\Enums\AnalyticsGranularity;
use Linqelio\Laravel\Data\Enums\AnalyticsMetric;
use Linqelio\Laravel\Data\Enums\AnalyticsReportKind;
use Linqelio\Laravel\Data\Enums\CampaignRecipientState;
use Linqelio\Laravel\Data\Enums\CampaignStatus;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\ContactStatus;
use Linqelio\Laravel\Data\Enums\ErrorCode;
use Linqelio\Laravel\Data\Enums\MessageDirection;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Enums\ScheduledSendStatus;
use Linqelio\Laravel\Data\Imports\ContactExportFilter;
use Linqelio\Laravel\Data\Imports\ImportColumn;
use Linqelio\Laravel\Data\Imports\ImportMapping;
use Linqelio\Laravel\Data\Policy\SendTemplate;
use Linqelio\Laravel\Facades\Linqelio;
use Symfony\Component\Yaml\Yaml;

/**
 * Reconciles this package against the OpenAPI contract it wraps.
 *
 * A hand-written client drifts silently: the server grows an operation, nobody
 * notices, and the gap only surfaces when somebody needs it. This gate makes
 * that visible — every operation must be either implemented or listed as
 * deliberately out of scope, and every error code must be known.
 *
 * Coverage is the cheap half. The expensive half is what a covered operation
 * puts on the wire: a wrapper can name every operation correctly and still send
 * `cursor` where the contract takes `before`, or read `items` where the body
 * says `messages`. Neither shows up as a failure — the first pages forever, the
 * second returns nothing — so the last three gates drive each wrapper for real
 * and compare the request and the reading against the contract itself.
 *
 * The fixture is a copy of the contract, refreshed with `composer contract:sync`.
 */
final class Contract
{
    /** @var array<string, string> operationId => the method that covers it */
    public const COVERED = [
        'listChannels' => 'channels()->list()',
        'createChannel' => 'channels()->create()',
        'connectChannel' => 'channels()->connect()',
        'disconnectChannel' => 'channels()->disconnect()',
        'getChannelStatus' => 'channels()->status()',
        'setChannelCredentials' => 'channels()->setCredentials()',
        'updateChannelSettings' => 'channels()->settings()',
        'syncChannel' => 'channels()->sync()',
        'deleteChannel' => 'channels()->delete()',

        'listContacts' => 'contacts()->list()',
        'getContact' => 'contacts()->find()',
        'createContact' => 'contacts()->create()',
        'updateContact' => 'contacts()->update()',
        'createContactInvite' => 'contacts()->invite()',
        'eraseContact' => 'contacts()->erase()',

        'listWebhooks' => 'webhooks()->list()',
        'registerWebhook' => 'webhooks()->register()',
        'updateWebhook' => 'webhooks()->disable() / enable()',
        'deleteWebhook' => 'webhooks()->delete()',

        'sendContactMessage' => 'messages()->send()',
        'listContactMessages' => 'messages()->history()',
        'getMessage' => 'messages()->find()',

        'listConversations' => 'conversations()->list()',
        'listConversationMessages' => 'conversations()->feed()',

        'uploadMedia' => 'media()->upload()',
        'getMessageMedia' => 'media()->fetch()',

        'createEmbedSession' => 'embed()->session()',

        // Issue #9: consent, typed fields, the send-policy dry run.
        'listContactConsents' => 'contacts()->consents()',
        'grantContactConsent' => 'contacts()->grantConsent()',
        'revokeContactConsent' => 'contacts()->revokeConsent()',
        'getContactFields' => 'contacts()->fields()',
        'setContactFields' => 'contacts()->setFields()',
        'listContactFields' => 'contacts()->fieldDefinitions()',
        'checkSendPolicy' => 'messages()->check()',
        'getContactAiProfile' => 'contacts()->aiProfile()',
        'fillContactAiProfile' => 'contacts()->fillAiProfile()',

        'sendConversationMessage' => 'conversations()->send()',
        'checkConversationSendPolicy' => 'conversations()->check()',
        'listConversationParticipants' => 'conversations()->participants()',

        'listChannelTemplates' => 'channels()->templates()',
        'syncChannelTemplates' => 'channels()->syncTemplates()',

        'createGroup' => 'groups()->create()',
        'addGroupParticipants' => 'groups()->addParticipants()',
        'removeGroupParticipants' => 'groups()->removeParticipants()',
        'updateGroup' => 'groups()->rename()',
        'leaveGroup' => 'groups()->leave()',
        'listChannelIgnoredGroups' => 'groups()->ignored()',

        'createCampaign' => 'campaigns()->create()',
        'listCampaigns' => 'campaigns()->list()',
        'getCampaign' => 'campaigns()->find()',
        'updateCampaign' => 'campaigns()->update()',
        'deleteCampaign' => 'campaigns()->delete()',
        'setCampaignAudience' => 'campaigns()->setAudience()',
        'dryRunCampaign' => 'campaigns()->dryRun()',
        'previewCampaignAudience' => 'campaigns()->previewAudience()',
        'launchCampaign' => 'campaigns()->launch()',
        'pauseCampaign' => 'campaigns()->pause()',
        'resumeCampaign' => 'campaigns()->resume()',
        'cancelCampaign' => 'campaigns()->cancel()',
        'listCampaignRecipients' => 'campaigns()->recipients()',

        'createScheduledSend' => 'scheduledSends()->create()',
        'listScheduledSends' => 'scheduledSends()->list()',
        'getScheduledSend' => 'scheduledSends()->find()',
        'updateScheduledSend' => 'scheduledSends()->update()',
        'cancelScheduledSend' => 'scheduledSends()->cancel()',

        'listChannelHealth' => 'health()->list()',
        'getChannelHealth' => 'health()->find()',
        'getChannelHealthHistory' => 'health()->history()',

        'listAlerts' => 'alerts()->list()',
        'getAlert' => 'alerts()->find()',
        'acknowledgeAlert' => 'alerts()->acknowledge()',
        'resolveAlert' => 'alerts()->resolve()',
        'listAlertSubscriptions' => 'alerts()->subscriptions()',
        'createAlertSubscription' => 'alerts()->subscribe()',
        'updateAlertSubscription' => 'alerts()->updateSubscription()',
        'deleteAlertSubscription' => 'alerts()->unsubscribe()',

        'uploadContactImport' => 'contactImports()->upload()',
        'previewContactImport' => 'contactImports()->preview()',
        'startContactImport' => 'contactImports()->start()',
        'cancelContactImport' => 'contactImports()->cancel()',
        'getContactImport' => 'contactImports()->find()',
        'listContactImports' => 'contactImports()->list()',
        'getContactImportReport' => 'contactImports()->report()',
        'createContactExport' => 'contactExports()->create()',
        'listContactExports' => 'contactExports()->list()',
        'getContactExport' => 'contactExports()->find()',
        'downloadContactExport' => 'contactExports()->download()',

        'getAnalyticsOverview' => 'analytics()->overview()',
        'getAnalyticsTimeseries' => 'analytics()->timeseries()',
        'getAnalyticsBreakdown' => 'analytics()->breakdown()',
        'getAnalyticsHeatmap' => 'analytics()->heatmap()',
        'getAnalyticsCampaignReport' => 'analytics()->campaign()',
        'createAnalyticsExport' => 'analytics()->export()',
        'listAnalyticsExports' => 'analytics()->exports()',
        'getAnalyticsExport' => 'analytics()->findExport()',
        'downloadAnalyticsExport' => 'analytics()->download()',
    ];

    /**
     * Operations this package does not wrap, and why. Being on this list is a
     * decision, not an oversight — which is the point of writing it down.
     *
     * @var array<string, string>
     */
    public const EXCLUDED = [
        'healthCheck' => 'infrastructure; an application has its own health checks',

        // Platform administration needs the platform:admin scope, which client
        // keys are not given. Wrapping it would suggest an application can call
        // it, and it cannot.
        'listCabinets' => 'platform:admin only',
        'createCabinet' => 'platform:admin only',
        'deleteCabinet' => 'platform:admin only — offboarding a tenant is the platform operator, not the tenant',
        'listCabinetKeys' => 'platform:admin only',
        'issueCabinetKey' => 'platform:admin only',
        'revokeCabinetKey' => 'platform:admin only',
        'rotateCabinetKey' => 'platform:admin only — an application cannot rotate the key it is holding',

        // The dead-letter window spans every cabinet and a replay sends traffic
        // to somebody else's endpoint. platform:admin by design, so a client key
        // cannot reach it and wrapping it here would suggest otherwise.
        'listDeadLetters' => 'platform:admin only',
        'replayDeadLetter' => 'platform:admin only',

        // Operator tooling rather than integration surface.
        'listAccessPool' => 'operator tooling',
        'addAccessPoolEntry' => 'operator tooling',
        'rotateAccessPool' => 'operator tooling',
        'setAccessPoolHealth' => 'operator tooling — which credential a cabinet sends through is an operator decision',
        'listAudit' => 'operator tooling',

        // Plane B: called by the widget with its own short-lived token, never by
        // the host application holding the client key.
        'getEmbedContact' => 'widget-side, embed token',
        'patchEmbedContact' => 'widget-side, embed token',
        'getEmbedConversation' => 'widget-side, embed token',
        'sendEmbedMessage' => 'widget-side, embed token',
        'startEmbedBotConversation' => 'widget-side, embed token',

        // Human identity (ADR-0049, ADR-0074): a person's own login, cookie
        // session and second factor. These accept a session cookie, never an API
        // key, so a package holding a client key cannot call them — wrapping them
        // would suggest it can.
        'login' => 'human identity — cookie session, not a client key',
        'logout' => 'human session only',
        'getMe' => 'human session only — x-permission self',
        'listMySessions' => 'human session only — x-permission self',
        'revokeMySession' => 'human session only — x-permission self',
        'acceptInvite' => 'human identity — the invitee holds no key',
        'requestPasswordReset' => 'human identity',
        'confirmPasswordReset' => 'human identity',
        'startTotpEnrollment' => 'human session only — x-permission self',
        'enableTotp' => 'human session only — x-permission self',
        'disableTotp' => 'human session only — x-permission self',
        'regenerateRecoveryCodes' => 'human session only — x-permission self',
        // ADR-0076: an existing account joins a cabinet from its own session.
        'previewMembershipInvite' => 'human session only — x-permission self',
        'acceptMembershipInvite' => 'human session only — x-permission self',

        // Cabinet settings (ADR-0057, issue #89): administered in the console.
        // A key with settings:manage can call them, but no integration need
        // exists yet; secrets there are write-only credentials of the cabinet.
        'getSettingsSection' => 'console cabinet settings — no integration use case yet',
        'putSettingsSection' => 'console cabinet settings — no integration use case yet',

        // Organizations (ADR-0079/0080): the platform surface is platform:admin,
        // which client keys are not given; the own-organization reads and the
        // requisites edit are console screens with no integration use case yet.
        'listOrganizations' => 'platform:admin only',
        'createOrganization' => 'platform:admin only',
        'getOrganization' => 'platform:admin only',
        'updateOrganization' => 'platform:admin only',
        'getOrganizationUsage' => 'platform:admin only',
        'listOrganizationAudit' => 'platform:admin only',
        'listPlans' => 'platform:admin only',
        'suspendOrganization' => 'platform:admin only',
        'resumeOrganization' => 'platform:admin only',
        'getMyOrganization' => 'console "My company" — no integration use case yet',
        'getMyOrganizationUsage' => 'console "My company" — no integration use case yet',
        'updateMyOrganization' => 'console "My company" requisites — needs billing:manage',

        // Round 8 (ADR-0061 §10). The field SCHEMA is edited in the console;
        // a host reads it (contacts()->fieldDefinitions()) and writes values.
        'putContactField' => 'console field schema editor — settings:manage',
        'deleteContactField' => 'console field schema editor — settings:manage',

        // Round 9 (ADR-0069, ADR-0089, ADR-0090).
        'streamEvents' => 'realtime SSE for the console/session (ADR-0069); a server-side host has no use for it yet',
        'streamEmbedEvents' => 'widget-side realtime stream, embed token',
        // UI round 2: the contacts table's extra columns (status, tags, last activity).
        'listContactDigests' => 'console contacts table columns — a host keeps its own view of its customers',
        // Contact merge (ADR-0090): deciding that two contacts are one person is
        // a human judgement over both records, made in the console's merge
        // review — the platform itself never merges on a guess, and the undo is
        // time-boxed. A host that could merge unattended would be exactly the
        // guessing the design rules out; hosts link their own records with
        // hostRefs instead. Wrap it when a host needs to show proposals, not
        // before.
        'listContactMergeProposals' => 'console merge review (ADR-0090) — a person decides that two contacts are one',
        'getContactMergeProposal' => 'console merge review (ADR-0090) — a person decides that two contacts are one',
        'dismissContactMergeProposal' => 'console merge review (ADR-0090) — a person decides that two contacts are one',
        'mergeContacts' => 'console merge review (ADR-0090) — an unattended host merge is the guess the design rules out',
        'listContactMerges' => 'console merge review (ADR-0090) — a person decides that two contacts are one',
        'getContactMerge' => 'console merge review (ADR-0090) — a person decides that two contacts are one',
        'undoContactMerge' => 'console merge review (ADR-0090) — time-boxed undo of a person\'s decision',

        // Round 10 (ADR-0062/0063/0064/0065/0092/0093).
        'listBitrixPortals' => 'Bitrix24 host surface (ADR-0093) — called by the portal page or the console',
        'unlinkBitrixPortal' => 'Bitrix24 host surface (ADR-0093) — called by the portal page or the console',
        'createBitrixPairing' => 'Bitrix24 host surface (ADR-0093) — called by the portal page or the console',
        'redeemBitrixPairing' => 'Bitrix24 host surface (ADR-0093) — called by the portal page or the console',
        'createBitrixAppSession' => 'Bitrix24 host surface (ADR-0093) — called by the portal page or the console',
        'confirmBitrixUserLink' => 'Bitrix24 host surface (ADR-0093) — called by the portal page or the console',
        'listAiRuns' => 'console AI settings (ADR-0064)',
        'testAiProvider' => 'console AI settings (ADR-0064)',
        'listAlertRules' => 'console alert rules (ADR-0065) — settings:manage',
        'putAlertRule' => 'console alert rules (ADR-0065) — settings:manage',
        'resetAlertRule' => 'console alert rules (ADR-0065) — settings:manage',

        // Round 11 (ADR-0066/0067/0068/0094/0096).
        'testAiGuard' => 'console AI guard settings (ADR-0068) — settings:manage',
        'listComplianceScans' => 'console compliance review (ADR-0067) — a supervisor or admin reviews findings',
        'startComplianceScan' => 'console compliance review (ADR-0067) — a supervisor or admin reviews findings',
        'getComplianceScan' => 'console compliance review (ADR-0067) — a supervisor or admin reviews findings',
        'cancelComplianceScan' => 'console compliance review (ADR-0067) — a supervisor or admin reviews findings',
        'listComplianceFindings' => 'console compliance review (ADR-0067) — a supervisor or admin reviews findings',
        'getComplianceFinding' => 'console compliance review (ADR-0067) — a supervisor or admin reviews findings',
        'actOnComplianceFinding' => 'console compliance review (ADR-0067) — a supervisor or admin reviews findings',
        'getBitrixConnector' => 'Bitrix24 connector settings (ADR-0094) — called by the console',
        'updateBitrixConnector' => 'Bitrix24 connector settings (ADR-0094) — called by the console',
        'listBitrixOpenLines' => 'Bitrix24 connector settings (ADR-0094) — called by the console',
        'bindBitrixChannelLine' => 'Bitrix24 connector settings (ADR-0094) — called by the console',
        'unbindBitrixChannelLine' => 'Bitrix24 connector settings (ADR-0094) — called by the console',
        'retryBitrixDeadLetters' => 'Bitrix24 connector settings (ADR-0094) — called by the console',

        // Round 12 (ADR-0097/0099/0100).
        'getBitrixCrmSettings' => 'Bitrix24 CRM settings (ADR-0097) — called by the console',
        'updateBitrixCrmSettings' => 'Bitrix24 CRM settings (ADR-0097) — called by the console',
        'listBitrixCrmFields' => 'Bitrix24 CRM settings (ADR-0097) — called by the console',
        'listBitrixLineRequests' => 'Bitrix24 CRM settings (ADR-0097) — called by the console',
        'createBitrixOpenLine' => 'Bitrix24 CRM settings (ADR-0097) — called by the console',
        'resolveEmbedAgentCrmCard' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'createEmbedAgentCrmContact' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'listEmbedAgentContactMessages' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'sendEmbedAgentMessage' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'checkEmbedAgentSendPolicy' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'listEmbedAgentChannelTemplates' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'uploadEmbedAgentMedia' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'getEmbedAgentMessageMedia' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'streamEmbedAgentEvents' => 'Bitrix24 CRM card widget (ADR-0099) — operator-token surface of the embed widget',
        'listAgentConversations' => 'Bitrix24 contact centre (ADR-0100) — operator-token surface of the embed widget',
        'markAgentConversationRead' => 'Bitrix24 contact centre (ADR-0100) — operator-token surface of the embed widget',
        'createAgentConversationSession' => 'Bitrix24 contact centre (ADR-0100) — operator-token surface of the embed widget',
        'listAgentChannelHealth' => 'Bitrix24 contact centre (ADR-0100) — operator-token surface of the embed widget',

        // Human platform operators (ADR-0075): platform:admin, which client
        // keys are not given — same reasoning as the cabinet operations above.
        'listPlatformOperators' => 'platform:admin only',
        'grantPlatformOperator' => 'platform:admin only',
        'revokePlatformOperator' => 'platform:admin only',

        // Team management (issue #31): people, roles and channel assignments of
        // a cabinet are administered in the console by a person. A key with
        // team:manage can call them, but no integration need for it exists yet;
        // wrapping them is a decision to take when one does, not by default.
        'listMembers' => 'console team management — no integration use case yet',
        'inviteMember' => 'console team management — the invite link is a secret meant for a person',
        'revokeInvite' => 'console team management — no integration use case yet',
        'updateMember' => 'console team management — no integration use case yet',
        'removeMember' => 'console team management — no integration use case yet',
    ];

    /**
     * Body fields this package sends that the contract does not declare.
     *
     * Each entry is a promise the platform does not currently keep. A field stays
     * on the wire because the argument behind it is public API and the platform
     * may yet honour it — but the wrapper's docblock has to tell the caller the
     * same thing this table does, so nobody plans around a guarantee that is not
     * there.
     *
     * EMPTY, and that is the interesting state. It held `_meta` on updateContact
     * and `cap`/`conversationId` on createEmbedSession until the platform started
     * honouring all three; the staleness gate below is what noticed, on the first
     * contract sync after they landed. A table like this is only trustworthy if
     * something forces entries out of it.
     *
     * @var array<string, array<string, string>>
     */
    public const WIRE_DEVIATIONS = [];
}

// ---------------------------------------------------------------------------
// Reading the contract
// ---------------------------------------------------------------------------

function contractPath(): string
{
    return __DIR__.'/../Fixtures/openapi.yaml';
}

/**
 * The contract, parsed once per process.
 *
 * Parsed rather than pattern-matched: the gates below need parameter names,
 * request body properties and response schemas, and a regex over YAML can see
 * none of those without reimplementing the format badly.
 *
 * @return array<string, mixed>
 */
function contract(): array
{
    static $parsed = null;

    if ($parsed === null) {
        /** @var array<string, mixed> $document */
        $document = Yaml::parseFile(contractPath());
        $parsed = $document;
    }

    return $parsed;
}

/**
 * operationId => the path, method and operation node behind it.
 *
 * @return array<string, array{path: string, method: string, op: array<string, mixed>}>
 */
function contractOperationNodes(): array
{
    static $nodes = null;

    if ($nodes !== null) {
        return $nodes;
    }

    $nodes = [];

    /** @var array<string, mixed> $paths */
    $paths = contract()['paths'] ?? [];

    foreach ($paths as $path => $item) {
        if (! is_array($item)) {
            continue;
        }

        foreach ($item as $method => $op) {
            if (is_array($op) && is_string($op['operationId'] ?? null)) {
                $nodes[$op['operationId']] = [
                    'path' => (string) $path,
                    'method' => strtoupper((string) $method),
                    'op' => $op,
                    // Parameters declared once for every method on the path.
                    'shared' => is_array($item['parameters'] ?? null) ? $item['parameters'] : [],
                ];
            }
        }
    }

    return $nodes;
}

/**
 * @return array<int, string>
 */
function contractOperations(): array
{
    return array_keys(contractOperationNodes());
}

/**
 * Follows `$ref` to the node it points at.
 *
 * @param  array<string, mixed>  $node
 * @return array<string, mixed>
 */
function contractResolve(array $node): array
{
    $hops = 0;

    while (is_string($node['$ref'] ?? null) && $hops++ < 10) {
        $target = contract();

        foreach (array_slice(explode('/', $node['$ref']), 1) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            $target = is_array($target) ? ($target[$segment] ?? []) : [];
        }

        $node = is_array($target) ? $target : [];
    }

    return $node;
}

/**
 * Every query parameter an operation accepts, path-level ones included.
 *
 * @return array<int, string>
 */
function contractQueryParams(string $operationId): array
{
    $node = contractOperationNodes()[$operationId] ?? null;

    if ($node === null) {
        return [];
    }

    /** @var array<int, mixed> $declared */
    $declared = array_merge(
        $node['shared'],
        is_array($node['op']['parameters'] ?? null) ? $node['op']['parameters'] : [],
    );

    $names = [];

    foreach ($declared as $parameter) {
        if (! is_array($parameter)) {
            continue;
        }

        $parameter = contractResolve($parameter);

        if (($parameter['in'] ?? null) === 'query' && is_string($parameter['name'] ?? null)) {
            $names[] = $parameter['name'];
        }
    }

    return array_values(array_unique($names));
}

/**
 * The properties an operation's JSON request body declares, or null when it
 * takes no JSON body or declares a free-form one — in which case there is no
 * list to compare against and the gate has nothing to say.
 *
 * @return array<int, string>|null
 */
function contractBodyFields(string $operationId): ?array
{
    $schema = contractOperationNodes()[$operationId]['op']['requestBody']['content']['application/json']['schema'] ?? null;

    if (! is_array($schema)) {
        return null;
    }

    $schema = contractResolve($schema);

    if (! is_array($schema['properties'] ?? null)) {
        return null;
    }

    return array_keys($schema['properties']);
}

/**
 * The field a page of results arrives under: the one array-typed property of an
 * operation's 200 body.
 *
 * Returns null when there is no single answer, which is the gate's cue to say
 * so rather than guess.
 */
function contractPageField(string $operationId): ?string
{
    $responses = contractOperationNodes()[$operationId]['op']['responses'] ?? [];

    $schema = $responses['200']['content']['application/json']['schema']
        ?? $responses[200]['content']['application/json']['schema']
        ?? null;

    if (! is_array($schema)) {
        return null;
    }

    $schema = contractResolve($schema);

    if (! is_array($schema['properties'] ?? null)) {
        return null;
    }

    $arrays = [];

    foreach ($schema['properties'] as $name => $property) {
        if (is_array($property) && (contractResolve($property)['type'] ?? null) === 'array') {
            $arrays[] = (string) $name;
        }
    }

    return count($arrays) === 1 ? $arrays[0] : null;
}

/**
 * @return array<int, string>
 */
function contractErrorCodes(): array
{
    $enum = contract()['components']['schemas']['ErrorCode']['enum'] ?? [];

    return is_array($enum) ? array_values(array_map(strval(...), $enum)) : [];
}

/**
 * Operations the platform replays under an Idempotency-Key (ADR-0103): marked
 * `x-idempotency: replay` in the contract.
 *
 * @return array<int, string>
 */
function contractReplayOperations(): array
{
    return array_keys(array_filter(
        contractOperationNodes(),
        static fn (array $node): bool => ($node['op']['x-idempotency'] ?? null) === 'replay',
    ));
}

// ---------------------------------------------------------------------------
// Driving the package
// ---------------------------------------------------------------------------

/**
 * How to perform each covered operation, with every optional argument supplied.
 *
 * Defaults are deliberately not used: an argument left out sends no parameter,
 * and a parameter that is never sent is a parameter this gate cannot check. A
 * `paged` entry additionally claims the call returns a mapped collection, which
 * is what lets the last gate tell "read the right field" from "read nothing".
 *
 * @return array<string, array{call: Closure, paged?: true}>
 */
function contractDrivers(): array
{
    return [
        'listChannels' => [
            'call' => fn (): array => Linqelio::channels()->list(),
            'paged' => true,
        ],
        'createChannel' => [
            'call' => fn () => Linqelio::channels()->create(ChannelKind::TgBot, 'Support'),
        ],
        'connectChannel' => [
            'call' => fn (): array => Linqelio::channels()->connect('ch-1'),
        ],
        'disconnectChannel' => [
            'call' => fn (): array => Linqelio::channels()->disconnect('ch-1'),
        ],
        'getChannelStatus' => [
            'call' => fn (): array => Linqelio::channels()->status('ch-1'),
        ],
        'setChannelCredentials' => [
            'call' => fn () => Linqelio::channels()->setCredentials('ch-1', 'tok', 'app-secret', 'verify-token'),
        ],
        'updateChannelSettings' => [
            'call' => fn (): array => Linqelio::channels()->settings('ch-1', '1234567890'),
        ],
        'syncChannel' => [
            // The options are the caller's, passed through verbatim, so the
            // package makes no claim about this body and the gate checks none.
            'call' => fn (): array => Linqelio::channels()->sync('ch-1'),
        ],
        'deleteChannel' => [
            'call' => fn () => Linqelio::channels()->delete('ch-1'),
        ],

        'listContacts' => [
            'call' => fn (): array => Linqelio::contacts()->list(cursor: 'cur-2', limit: 25, status: 'new', q: 'ann')['contacts'],
            'paged' => true,
        ],
        'getContact' => [
            'call' => fn () => Linqelio::contacts()->find('c-1'),
        ],
        'createContact' => [
            'call' => fn () => Linqelio::contacts()->create(
                ChannelKind::TgBot,
                phone: '+380500000000',
                username: 'ann',
                providerId: '629076487',
                name: 'Ann',
            ),
        ],
        'updateContact' => [
            // `custom` rather than anything else: the attributes are the caller's
            // to choose, so the gate should judge what the package adds, not what
            // this fixture happens to pass through.
            'call' => fn () => Linqelio::contacts()->update('c-1', ['custom' => ['tier' => 'gold']], version: 3),
        ],
        'createContactInvite' => [
            'call' => fn (): array => Linqelio::contacts()->invite('c-1', 'ch-1', idempotencyKey: 'k-1'),
        ],
        'eraseContact' => [
            'call' => fn () => Linqelio::contacts()->erase('c-1'),
        ],

        'listWebhooks' => [
            'call' => fn (): array => Linqelio::webhooks()->list(),
            'paged' => true,
        ],
        'registerWebhook' => [
            // `secret` is the one optional argument deliberately left out, and
            // the only exception to the rule above: it and `secretRef` are
            // mutually exclusive, so driving both would put a body on the wire
            // the platform answers 400 to. The wrapper refuses that combination
            // before the request — see WebhookSubscriptionTest.
            'call' => fn () => Linqelio::webhooks()->register(
                'https://app.test/linqelio/webhook',
                ['message.inbound'],
                'secret://webhooks/app',
                idempotencyKey: 'k-1',
            ),
        ],
        'updateWebhook' => [
            'call' => fn () => Linqelio::webhooks()->disable('wh-1'),
        ],
        'deleteWebhook' => [
            'call' => fn () => Linqelio::webhooks()->delete('wh-1'),
        ],

        'sendContactMessage' => [
            'call' => fn () => Linqelio::messages()->send(
                'c-1',
                MessageType::Text,
                ['text' => 'hello'],
                channelId: 'ch-1',
                replyTo: 'm-0',
                acknowledgedWarnings: ['policy.contact_frequency'],
                template: new SendTemplate('order_update', 'uk', ['A-17']),
            ),
        ],
        'checkSendPolicy' => [
            'call' => fn () => Linqelio::messages()->check(
                'c-1',
                MessageType::Text,
                ['text' => 'hello'],
                channelId: 'ch-1',
                replyTo: 'm-0',
                template: new SendTemplate('order_update', 'uk', ['A-17']),
            ),
        ],
        'listContactMessages' => [
            'call' => fn (): array => Linqelio::messages()->history('c-1', cursor: 'cur-2', limit: 50)['messages'],
            'paged' => true,
        ],
        'getMessage' => [
            'call' => fn () => Linqelio::messages()->find('01MSG'),
        ],

        'listConversations' => [
            'call' => fn (): array => Linqelio::conversations()->list(
                channelId: 'ch-1',
                status: 'open',
                since: 'cur-2',
                limit: 25,
            )['conversations'],
            'paged' => true,
        ],
        'listConversationMessages' => [
            'call' => fn (): array => Linqelio::conversations()->feed('cv-1', before: 'cur-2', limit: 50)['messages'],
            'paged' => true,
        ],

        'uploadMedia' => [
            'call' => fn () => Linqelio::media()->upload('bytes', 'photo.png', 'image/png'),
        ],
        'getMessageMedia' => [
            'call' => fn () => Linqelio::media()->fetch('m-1'),
        ],

        'createEmbedSession' => [
            'call' => fn (): array => Linqelio::embed()->session('c-1', ['read', 'write'], 'cv-1'),
        ],

        'listContactConsents' => [
            'call' => fn (): array => Linqelio::contacts()->consents('c-1'),
            'paged' => true,
        ],
        'grantContactConsent' => [
            'call' => fn () => Linqelio::contacts()->grantConsent('c-1', 'ch-1', 'crm-form-12'),
        ],
        'revokeContactConsent' => [
            'call' => fn () => Linqelio::contacts()->revokeConsent('c-1', 'ch-1'),
        ],
        'getContactFields' => [
            'call' => fn () => Linqelio::contacts()->fields('c-1'),
        ],
        'setContactFields' => [
            'call' => fn () => Linqelio::contacts()->setFields('c-1', ['tier' => 'gold', 'old' => null]),
        ],
        'listContactFields' => [
            'call' => fn (): array => Linqelio::contacts()->fieldDefinitions(),
            'paged' => true,
        ],
        'getContactAiProfile' => [
            'call' => fn () => Linqelio::contacts()->aiProfile('c-1'),
        ],
        'fillContactAiProfile' => [
            'call' => fn () => Linqelio::contacts()->fillAiProfile('c-1', idempotencyKey: 'k-1'),
        ],

        'sendConversationMessage' => [
            'call' => fn () => Linqelio::conversations()->send(
                'cv-1',
                MessageType::Text,
                ['text' => 'hello'],
                channelId: 'ch-1',
                replyTo: 'm-0',
                idempotencyKey: 'k-1',
                acknowledgedWarnings: [],
                template: new SendTemplate('order_update', 'uk'),
            ),
        ],
        'checkConversationSendPolicy' => [
            'call' => fn () => Linqelio::conversations()->check(
                'cv-1',
                MessageType::Text,
                ['text' => 'hello'],
                channelId: 'ch-1',
                replyTo: 'm-0',
                template: new SendTemplate('order_update', 'uk'),
            ),
        ],
        'listConversationParticipants' => [
            'call' => fn (): array => Linqelio::conversations()->participants('cv-1'),
            'paged' => true,
        ],

        'listChannelTemplates' => [
            'call' => fn (): array => Linqelio::channels()->templates('ch-1')->templates,
            'paged' => true,
        ],
        'syncChannelTemplates' => [
            'call' => fn () => Linqelio::channels()->syncTemplates('ch-1'),
        ],

        'createGroup' => [
            'call' => fn () => Linqelio::groups()->create('ch-1', 'Team', ['c-1', 'c-2'], idempotencyKey: 'k-1'),
        ],
        'addGroupParticipants' => [
            'call' => fn () => Linqelio::groups()->addParticipants('cv-1', ['c-3'], idempotencyKey: 'k-1'),
        ],
        'removeGroupParticipants' => [
            'call' => fn () => Linqelio::groups()->removeParticipants('cv-1', ['380500000000'], idempotencyKey: 'k-1'),
        ],
        'updateGroup' => [
            'call' => fn () => Linqelio::groups()->rename('cv-1', 'New name'),
        ],
        'leaveGroup' => [
            'call' => fn () => Linqelio::groups()->leave('cv-1', idempotencyKey: 'k-1'),
        ],
        'listChannelIgnoredGroups' => [
            'call' => fn (): array => Linqelio::groups()->ignored('ch-1')->groups,
            'paged' => true,
        ],

        'createCampaign' => [
            'call' => fn () => Linqelio::campaigns()->create(contractCampaignInput(), idempotencyKey: 'k-1'),
        ],
        'listCampaigns' => [
            'call' => fn (): array => Linqelio::campaigns()->list(CampaignStatus::Running, 'cur-2', 25)['campaigns'],
            'paged' => true,
        ],
        'getCampaign' => [
            'call' => fn () => Linqelio::campaigns()->find('cmp-1'),
        ],
        'updateCampaign' => [
            'call' => fn () => Linqelio::campaigns()->update('cmp-1', contractCampaignInput()),
        ],
        'deleteCampaign' => [
            'call' => fn (): bool => Linqelio::campaigns()->delete('cmp-1'),
        ],
        'setCampaignAudience' => [
            'call' => fn () => Linqelio::campaigns()->setAudience('cmp-1', contractAudience()),
        ],
        'dryRunCampaign' => [
            'call' => fn () => Linqelio::campaigns()->dryRun('cmp-1'),
        ],
        'previewCampaignAudience' => [
            'call' => fn () => Linqelio::campaigns()->previewAudience(['ch-1'], contractAudience()),
        ],
        'launchCampaign' => [
            'call' => fn () => Linqelio::campaigns()->launch('cmp-1', idempotencyKey: 'k-1'),
        ],
        'pauseCampaign' => [
            'call' => fn () => Linqelio::campaigns()->pause('cmp-1'),
        ],
        'resumeCampaign' => [
            'call' => fn () => Linqelio::campaigns()->resume('cmp-1'),
        ],
        'cancelCampaign' => [
            'call' => fn () => Linqelio::campaigns()->cancel('cmp-1'),
        ],
        'listCampaignRecipients' => [
            'call' => fn (): array => Linqelio::campaigns()->recipients(
                'cmp-1',
                CampaignRecipientState::Failed,
                'cur-2',
                25,
            )['recipients'],
            'paged' => true,
        ],

        'createScheduledSend' => [
            'call' => fn () => Linqelio::scheduledSends()->create(
                'c-1',
                new DateTimeImmutable('2026-10-01T09:00:00+03:00'),
                MessageType::Text,
                ['text' => 'hello'],
                channelId: 'ch-1',
                replyTo: 'm-0',
                idempotencyKey: 'k-1',
            ),
        ],
        'listScheduledSends' => [
            'call' => fn (): array => Linqelio::scheduledSends()->list(
                'c-1',
                ScheduledSendStatus::Scheduled,
                'cur-2',
                25,
            )['scheduledSends'],
            'paged' => true,
        ],
        'getScheduledSend' => [
            'call' => fn () => Linqelio::scheduledSends()->find('s-1'),
        ],
        'updateScheduledSend' => [
            'call' => fn () => Linqelio::scheduledSends()->update(
                's-1',
                new DateTimeImmutable('2026-10-02T09:00:00+03:00'),
                MessageType::Text,
                ['text' => 'later'],
            ),
        ],
        'cancelScheduledSend' => [
            'call' => fn () => Linqelio::scheduledSends()->cancel('s-1'),
        ],

        'listChannelHealth' => [
            'call' => fn (): array => Linqelio::health()->list(),
            'paged' => true,
        ],
        'getChannelHealth' => [
            'call' => fn () => Linqelio::health()->find('ch-1'),
        ],
        'getChannelHealthHistory' => [
            'call' => fn (): array => Linqelio::health()->history('ch-1', new DateTimeImmutable('2026-09-01T00:00:00Z'), 100),
            'paged' => true,
        ],

        'listAlerts' => [
            'call' => fn (): array => Linqelio::alerts()->list(
                AlertStatus::Open,
                'ch-1',
                'cur-2',
                25,
                AlertState::Active,
                new DateTimeImmutable('2026-09-25T00:00:00+00:00'),
            )['alerts'],
            'paged' => true,
        ],
        'getAlert' => [
            'call' => fn () => Linqelio::alerts()->find('al-1'),
        ],
        'acknowledgeAlert' => [
            'call' => fn () => Linqelio::alerts()->acknowledge('al-1'),
        ],
        'resolveAlert' => [
            'call' => fn () => Linqelio::alerts()->resolve('al-1'),
        ],
        'listAlertSubscriptions' => [
            'call' => fn (): array => Linqelio::alerts()->subscriptions(),
            'paged' => true,
        ],
        'createAlertSubscription' => [
            'call' => fn () => Linqelio::alerts()->subscribe(
                AlertDeliveryChannel::Webhook,
                webhookId: 'wh-1',
                role: 'admin',
                minSeverity: AlertSeverity::Warning,
                ruleTypes: [AlertType::ChannelDisconnected],
                channelIds: ['ch-1'],
                enabled: true,
                idempotencyKey: 'k-1',
            ),
        ],
        'updateAlertSubscription' => [
            'call' => fn () => Linqelio::alerts()->updateSubscription(
                'sub-1',
                AlertSeverity::Critical,
                [AlertType::ChannelHealthLow],
                ['ch-1'],
                false,
            ),
        ],
        'deleteAlertSubscription' => [
            'call' => fn () => Linqelio::alerts()->unsubscribe('sub-1'),
        ],

        'uploadContactImport' => [
            'call' => fn () => Linqelio::contactImports()->upload("phone\n380500000000\n", 'customers.csv', idempotencyKey: 'k-1'),
        ],
        'previewContactImport' => [
            'call' => fn () => Linqelio::contactImports()->preview('job-1', contractMapping(), 20),
        ],
        'startContactImport' => [
            'call' => fn () => Linqelio::contactImports()->start('job-1', contractMapping(), idempotencyKey: 'k-1'),
        ],
        'cancelContactImport' => [
            'call' => fn () => Linqelio::contactImports()->cancel('job-1'),
        ],
        'getContactImport' => [
            'call' => fn () => Linqelio::contactImports()->find('job-1'),
        ],
        'listContactImports' => [
            'call' => fn (): array => Linqelio::contactImports()->list(),
            'paged' => true,
        ],
        'getContactImportReport' => [
            'call' => fn () => Linqelio::contactImports()->report('job-1'),
        ],
        'createContactExport' => [
            'call' => fn () => Linqelio::contactExports()->create(new ContactExportFilter(
                tags: ['vip'],
                channelIds: ['ch-1'],
                fields: ['tier' => 'gold'],
                status: ContactStatus::Active,
            ), idempotencyKey: 'k-1'),
        ],
        'listContactExports' => [
            'call' => fn (): array => Linqelio::contactExports()->list(),
            'paged' => true,
        ],
        'getContactExport' => [
            'call' => fn () => Linqelio::contactExports()->find('exp-1'),
        ],
        'downloadContactExport' => [
            'call' => fn () => Linqelio::contactExports()->download('exp-1'),
        ],

        'getAnalyticsOverview' => [
            'call' => fn () => Linqelio::analytics()->overview('2026-09-01', '2026-09-30', 'ch-1'),
        ],
        'getAnalyticsTimeseries' => [
            'call' => fn () => Linqelio::analytics()->timeseries(
                AnalyticsMetric::MessagesOut,
                AnalyticsGranularity::Day,
                new DateTimeImmutable('2026-09-01'),
                '2026-09-30',
                'ch-1',
            ),
        ],
        'getAnalyticsBreakdown' => [
            'call' => fn () => Linqelio::analytics()->breakdown(AnalyticsBreakdownBy::Channel, '2026-09-01', '2026-09-30', 'ch-1'),
        ],
        'getAnalyticsHeatmap' => [
            'call' => fn () => Linqelio::analytics()->heatmap(MessageDirection::Inbound, '2026-09-01', '2026-09-30', 'ch-1'),
        ],
        'getAnalyticsCampaignReport' => [
            'call' => fn () => Linqelio::analytics()->campaign('cmp-1'),
        ],
        'createAnalyticsExport' => [
            'call' => fn () => Linqelio::analytics()->export(new AnalyticsExportRequest(
                report: AnalyticsReportKind::Timeseries,
                format: AnalyticsExportFormat::Xlsx,
                from: '2026-09-01',
                to: '2026-09-30',
                channelId: 'ch-1',
                metric: AnalyticsMetric::MessagesOut,
                granularity: AnalyticsGranularity::Week,
                by: AnalyticsBreakdownBy::Channel,
                campaignId: 'cmp-1',
            ), idempotencyKey: 'k-1'),
        ],
        'listAnalyticsExports' => [
            'call' => fn (): array => Linqelio::analytics()->exports(),
            'paged' => true,
        ],
        'getAnalyticsExport' => [
            'call' => fn () => Linqelio::analytics()->findExport('ax-1'),
        ],
        'downloadAnalyticsExport' => [
            'call' => fn () => Linqelio::analytics()->download('ax-1'),
        ],
    ];
}

/**
 * A campaign draft with every member set, so the body gate sees them all.
 */
function contractCampaignInput(): CampaignInput
{
    return new CampaignInput(
        name: 'Autumn sale',
        channelIds: ['ch-1', 'ch-2'],
        content: CampaignContent::template(new CampaignTemplate(
            name: 'sale',
            language: 'uk',
            params: [CampaignTemplateParam::contactName('friend'), CampaignTemplateParam::literal('20%')],
        )),
        audience: contractAudience(),
        startAt: new DateTimeImmutable('2026-10-01T09:00:00+03:00'),
        timeZone: 'Europe/Kyiv',
        window: new CampaignWindow('09:00', '20:00'),
        ratePerMinute: 30,
        maxAttempts: 3,
    );
}

function contractAudience(): CampaignAudience
{
    return new CampaignAudience(
        contactIds: ['c-1'],
        tags: ['vip'],
        fields: ['tier' => 'gold'],
        status: 'active',
        all: true,
    );
}

function contractMapping(): ImportMapping
{
    return new ImportMapping(
        ImportColumn::identity(0, ChannelKind::WaWeb),
        ImportColumn::hostRef(1, 'crm'),
        ImportColumn::field(2, 'tier'),
        ImportColumn::consent(3, 'ch-1'),
    );
}

/**
 * Runs one wrapper against a faked platform and hands back the request it made.
 *
 * @return array{request: Request, result: mixed}
 */
function driveOperation(string $operationId): array
{
    $driver = contractDrivers()[$operationId];

    $pageField = contractPageField($operationId);

    // A page carries exactly one element, and the element is empty: every mapper
    // in this package defaults its fields, so an empty element proves the field
    // NAME was found without the fixture having to restate the whole schema.
    $body = isset($driver['paged']) && $pageField !== null ? [$pageField => [[]]] : [];

    Http::fake(['*' => Http::response($body, 200)]);

    $result = ($driver['call'])();

    /** @var array{0: Request, 1: mixed}|null $recorded */
    $recorded = Http::recorded()->first();

    expect($recorded)->not->toBeNull("{$operationId}: the wrapper made no HTTP call at all.");

    return ['request' => $recorded[0], 'result' => $result];
}

/**
 * @return array<int, string>
 */
function sentQueryKeys(Request $request): array
{
    $query = [];
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return array_keys($query);
}

// ---------------------------------------------------------------------------
// Coverage
// ---------------------------------------------------------------------------

it('has the contract fixture available', function (): void {
    expect(file_exists(contractPath()))->toBeTrue(
        'tests/Fixtures/openapi.yaml is missing — run composer contract:sync'
    );
    expect(contractOperations())->not->toBeEmpty();
});

it('classifies every contract operation as covered or deliberately excluded', function (): void {
    $unclassified = array_diff(
        contractOperations(),
        array_keys(Contract::COVERED),
        array_keys(Contract::EXCLUDED),
    );

    expect($unclassified)->toBeEmpty(sprintf(
        "The contract grew operations this package neither wraps nor excludes:\n  - %s\n".
        'Add each to Contract::COVERED or Contract::EXCLUDED with a reason.',
        implode("\n  - ", $unclassified),
    ));
});

it('does not claim operations the contract no longer has', function (): void {
    $stale = array_diff(
        array_merge(array_keys(Contract::COVERED), array_keys(Contract::EXCLUDED)),
        contractOperations(),
    );

    expect($stale)->toBeEmpty(sprintf(
        'These are listed here but absent from the contract: %s',
        implode(', ', $stale),
    ));
});

it('knows every error code the contract defines', function (): void {
    $unknown = array_filter(
        contractErrorCodes(),
        static fn (string $code): bool => ErrorCode::tryFrom($code) === null,
    );

    expect($unknown)->toBeEmpty(sprintf(
        'ErrorCode is missing: %s. The registry is additive, so add the cases — '.
        'unknown codes still work, but callers lose the ability to match on them.',
        implode(', ', $unknown),
    ));
});

it('defines no error code the contract does not', function (): void {
    $contractCodes = contractErrorCodes();

    $extra = array_filter(
        array_map(static fn (ErrorCode $c): string => $c->value, ErrorCode::cases()),
        static fn (string $code): bool => $code !== 'unknown' && ! in_array($code, $contractCodes, true),
    );

    expect($extra)->toBeEmpty(sprintf('Not in the contract: %s', implode(', ', $extra)));
});

it('drives every covered operation, so the wire gates cannot skip one', function (): void {
    $undriven = array_diff(array_keys(Contract::COVERED), array_keys(contractDrivers()));

    expect($undriven)->toBeEmpty(sprintf(
        'Covered but never exercised, so nothing below checks what they send: %s. '.
        'Add each to contractDrivers().',
        implode(', ', $undriven),
    ));
});

// ---------------------------------------------------------------------------
// The wire
// ---------------------------------------------------------------------------

it('sends only query parameters the contract declares', function (string $operationId): void {
    $sent = sentQueryKeys(driveOperation($operationId)['request']);
    $declared = contractQueryParams($operationId);

    $undeclared = array_diff($sent, $declared);

    expect($undeclared)->toBeEmpty(sprintf(
        "%s (%s) sends %s, which %s does not accept. It takes: %s.\n".
        'An undeclared parameter is ignored by the server, so this fails silently in production — '.
        'a filter that never filters, or a cursor that pages forever.',
        Contract::COVERED[$operationId],
        $operationId,
        implode(', ', $undeclared),
        $operationId,
        $declared === [] ? '(nothing)' : implode(', ', $declared),
    ));
})->with(fn (): array => array_keys(contractDrivers()));

it('sends only request body fields the contract declares', function (string $operationId): void {
    $declared = contractBodyFields($operationId);

    if ($declared === null) {
        expect(true)->toBeTrue();

        return;
    }

    $request = driveOperation($operationId)['request'];

    $sent = $request->method() === 'GET' ? [] : array_keys($request->data());

    $undeclared = array_diff($sent, $declared, array_keys(Contract::WIRE_DEVIATIONS[$operationId] ?? []));

    expect($undeclared)->toBeEmpty(sprintf(
        "%s (%s) sends %s in its body, which %s does not declare. It takes: %s.\n".
        'An undeclared field is decoded into nothing, so the caller gets no error and no effect. '.
        'Fix the wrapper, or record it in Contract::WIRE_DEVIATIONS with what the platform actually does.',
        Contract::COVERED[$operationId],
        $operationId,
        implode(', ', $undeclared),
        $operationId,
        implode(', ', $declared),
    ));
})->with(fn (): array => array_keys(contractDrivers()));

// Not a dataset: an EMPTY deviation table is the healthy state, and a dataset
// cannot be empty. It is also the state this gate produced — it caught all three
// recorded deviations the moment the platform started honouring them.
it('keeps no stale wire deviation', function (): void {
    foreach (Contract::WIRE_DEVIATIONS as $operationId => $fields) {
        $recorded = array_keys($fields);
        $declared = contractBodyFields($operationId) ?? [];

        $request = driveOperation($operationId)['request'];
        $sent = $request->method() === 'GET' ? [] : array_keys($request->data());

        // Still sent: an entry for a field the package stopped sending is a note
        // about code that no longer exists.
        expect(array_diff($recorded, $sent))->toBeEmpty(
            "{$operationId}: WIRE_DEVIATIONS lists fields this package no longer sends. Delete them."
        );

        // Still undeclared: once the contract grows the field, the deviation is
        // the stale thing, and leaving it would suppress a future real failure.
        expect(array_intersect($recorded, $declared))->toBeEmpty(
            "{$operationId}: the contract now declares these, so they are not deviations any more. ".
            'Delete the entries and let the gate check them normally.'
        );
    }

    expect(true)->toBeTrue();
});

it('reads each page out of the field the contract names', function (string $operationId): void {
    $pageField = contractPageField($operationId);

    expect($pageField)->not->toBeNull(sprintf(
        '%s is driven as a page, but %s declares no single array field in its 200 body. '.
        'Either it is not a page, or the response now has more than one collection and this gate needs telling which.',
        Contract::COVERED[$operationId],
        $operationId,
    ));

    $result = driveOperation($operationId)['result'];

    expect($result)->toBeArray()->toHaveCount(1, sprintf(
        '%s (%s) returned nothing from a body carrying one element under `%s`. '.
        'It is reading some other field — which in production is an empty page, not an error.',
        Contract::COVERED[$operationId],
        $operationId,
        $pageField ?? '?',
    ));
})->with(fn (): array => array_keys(array_filter(contractDrivers(), fn (array $d): bool => isset($d['paged']))));

// ---------------------------------------------------------------------------
// Idempotency (ADR-0103)
// ---------------------------------------------------------------------------

// Every operation the platform replays under a key must let the caller pin that
// key: the generated one is only good for the transport's own retries, and a
// retry from another process — a queue re-run, a redeploy — needs a key the
// caller owns. The drivers pass 'k-1'; anything else on the wire means the
// wrapper dropped or replaced it.
it('lets the caller pin the Idempotency-Key on every replayable operation it covers', function (string $operationId): void {
    $request = driveOperation($operationId)['request'];

    expect($request->header('Idempotency-Key'))->toBe(['k-1'], sprintf(
        '%s (%s) is `x-idempotency: replay` in the contract, but the wrapper did not send the caller\'s key. '.
        'Add a ?string $idempotencyKey argument and pass it to the client.',
        Contract::COVERED[$operationId],
        $operationId,
    ));
})->with(fn (): array => array_values(array_intersect(contractReplayOperations(), array_keys(Contract::COVERED))));
