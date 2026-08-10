<?php

declare(strict_types=1);

use Linqelio\Laravel\Data\Enums\ErrorCode;

/**
 * Reconciles this package against the OpenAPI contract it wraps.
 *
 * A hand-written client drifts silently: the server grows an operation, nobody
 * notices, and the gap only surfaces when somebody needs it. This gate makes
 * that visible — every operation must be either implemented or listed as
 * deliberately out of scope, and every error code must be known.
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

        'listConversations' => 'conversations()->list()',
        'listConversationMessages' => 'conversations()->feed()',

        'uploadMedia' => 'media()->upload()',
        'getMessageMedia' => 'media()->fetch()',

        'createEmbedSession' => 'embed()->session()',
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

        // Operator tooling rather than integration surface.
        'listAccessPool' => 'operator tooling',
        'addAccessPoolEntry' => 'operator tooling',
        'rotateAccessPool' => 'operator tooling',
        'listAudit' => 'operator tooling',

        // Plane B: called by the widget with its own short-lived token, never by
        // the host application holding the client key.
        'getEmbedContact' => 'widget-side, embed token',
        'patchEmbedContact' => 'widget-side, embed token',
        'getEmbedConversation' => 'widget-side, embed token',
        'sendEmbedMessage' => 'widget-side, embed token',
        'startEmbedBotConversation' => 'widget-side, embed token',
    ];
}

function contractPath(): string
{
    return __DIR__.'/../Fixtures/openapi.yaml';
}

/**
 * @return array<int, string>
 */
function contractOperations(): array
{
    $yaml = file_get_contents(contractPath());

    if ($yaml === false) {
        return [];
    }

    preg_match_all('/^\s*operationId:\s*(\w+)/m', $yaml, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * @return array<int, string>
 */
function contractErrorCodes(): array
{
    $yaml = file_get_contents(contractPath());

    if ($yaml === false || ! preg_match('/ErrorCode:.*?enum:\s*\n((?:\s*-\s*[\w.]+\n)+)/s', $yaml, $m)) {
        return [];
    }

    preg_match_all('/-\s*([\w.]+)/', $m[1], $codes);

    return $codes[1];
}

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
    $operations = contractOperations();

    $stale = array_diff(
        array_merge(array_keys(Contract::COVERED), array_keys(Contract::EXCLUDED)),
        $operations,
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
