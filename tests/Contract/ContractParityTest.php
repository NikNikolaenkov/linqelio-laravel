<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\ErrorCode;
use Linqelio\Laravel\Data\Enums\MessageType;
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
            'call' => fn (): array => Linqelio::contacts()->invite('c-1', 'ch-1'),
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
    ];
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
