<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Enums\PolicyVerdict;
use Linqelio\Laravel\Data\Policy\SendTemplate;
use Linqelio\Laravel\Exceptions\PolicyException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Check → confirm → send (issue #9, ADR-0082).
 *
 * The check and the send must describe the SAME message, or the confirmation
 * is about something else than what goes out — so both are driven here and
 * their bodies compared.
 */
function policyCheckBody(): array
{
    return [
        'verdict' => 'warn',
        'sendable' => true,
        'warningKeys' => ['policy.contact_frequency'],
        'findings' => [
            ['check' => 'rate', 'verdict' => 'allow', 'key' => 'rate'],
            [
                'check' => 'frequency',
                'verdict' => 'warn',
                'key' => 'policy.contact_frequency',
                'code' => 'policy.contact_frequency',
                'params' => ['count' => '3'],
                'detail' => 'Third message today',
            ],
        ],
        'meters' => [['check' => 'rate', 'name' => 'rate.burst', 'used' => 3, 'limit' => 20]],
        'channelId' => 'ch-1',
        'cost' => 1,
        'evaluatedAt' => '2026-09-24T10:00:00Z',
    ];
}

it('checks a send without sending it, and reads the whole answer', function (): void {
    Http::fake(['*/contacts/c-1/messages/policy-check' => Http::response(policyCheckBody())]);

    $check = Linqelio::messages()->check('c-1', MessageType::Text, ['text' => 'hi'], channelId: 'ch-1');

    expect($check->verdict)->toBe(PolicyVerdict::Warn)
        ->and($check->sendable)->toBeTrue()
        ->and($check->needsConfirmation())->toBeTrue()
        ->and($check->warningKeys)->toBe(['policy.contact_frequency'])
        ->and($check->findings)->toHaveCount(2)
        ->and($check->warnings())->toHaveCount(1)
        ->and($check->warnings()[0]->params)->toBe(['count' => '3'])
        ->and($check->warnings()[0]->message())->toBe('Third message today')
        ->and($check->blockers())->toBe([])
        ->and($check->meters[0]->remaining())->toBe(17)
        ->and($check->channelId)->toBe('ch-1');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/contacts/c-1/messages/policy-check')
        && $r->data() === ['type' => 'text', 'content' => ['text' => 'hi'], 'channelId' => 'ch-1']);
});

it('sends with the confirmed warnings, in the body the check was asked about', function (): void {
    Http::fake([
        '*/policy-check' => Http::response(policyCheckBody()),
        '*/contacts/c-1/messages' => Http::response(['id' => '01A', 'type' => 'text', 'status' => 'queued', 'policyWarnings' => [
            ['check' => 'frequency', 'verdict' => 'warn', 'key' => 'policy.contact_frequency'],
        ]], 202),
    ]);

    $check = Linqelio::messages()->check('c-1', MessageType::Text, ['text' => 'hi']);
    $message = Linqelio::messages()->send('c-1', MessageType::Text, ['text' => 'hi'], acknowledgedWarnings: $check->warningKeys);

    expect($message->policyWarnings)->toHaveCount(1)
        ->and($message->policyWarnings[0]->key)->toBe('policy.contact_frequency');

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/contacts/c-1/messages')
        && $r->hasHeader('Idempotency-Key')
        && $r->data() === ['type' => 'text', 'content' => ['text' => 'hi'], 'acknowledgedWarnings' => ['policy.contact_frequency']]);
});

it('confirms "no warnings" with an empty list, and leaves the field out of an unconfirmed send', function (): void {
    Http::fake(['*' => Http::response(['id' => '01A', 'type' => 'text'], 202)]);

    Linqelio::messages()->send('c-1', MessageType::Text, ['text' => 'a'], acknowledgedWarnings: []);
    Linqelio::messages()->send('c-1', MessageType::Text, ['text' => 'b']);

    $bodies = Http::recorded()->map(fn (array $pair): array => $pair[0]->data())->all();

    expect($bodies[0])->toHaveKey('acknowledgedWarnings', [])
        ->and($bodies[1])->not->toHaveKey('acknowledgedWarnings');
});

it('sends a template without content — the template carries the message (issue #140)', function (): void {
    Http::fake(['*' => Http::response(['id' => '01A', 'type' => 'template'], 202)]);

    Linqelio::messages()->sendTemplate('c-1', new SendTemplate('order_update', 'uk', ['A-17']), idempotencyKey: 'order-17');

    Http::assertSent(fn (Request $r): bool => $r->header('Idempotency-Key')[0] === 'order-17'
        && ! str_contains($r->body(), '"content"')
        && $r['template'] === ['name' => 'order_update', 'language' => 'uk', 'params' => ['A-17']]
        && $r['type'] === 'template');
});

it('leaves content out of every template body: contact and conversation, send and check', function (): void {
    Http::fake(['*' => Http::response(['id' => '01A', 'type' => 'template', 'verdict' => 'allow'], 202)]);

    $template = new SendTemplate('order_update', 'uk', ['A-17']);

    Linqelio::messages()->check('c-1', MessageType::Template, [], template: $template);
    Linqelio::conversations()->send('cv-1', MessageType::Template, [], template: $template);
    Linqelio::conversations()->check('cv-1', MessageType::Template, [], template: $template);

    $bodies = Http::recorded()->map(fn (array $pair): array => $pair[0]->data())->all();

    expect($bodies)->toHaveCount(3);
    foreach ($bodies as $body) {
        expect($body)->not->toHaveKey('content')
            ->and($body['type'])->toBe('template')
            ->and($body['template']['name'])->toBe('order_update');
    }
});

it('sends an empty content as `[]`, which the platform reads as `{}`', function (): void {
    Http::fake(['*' => Http::response(['id' => '01A', 'type' => 'text'], 202)]);

    Linqelio::messages()->send('c-1', MessageType::Text, []);

    Http::assertSent(fn (Request $r): bool => str_contains($r->body(), '"content":[]'));
});

it('reads the unacknowledged warnings off a confirmation refusal', function (): void {
    Http::fake(['*' => Http::response([
        'code' => 'policy.confirmation_required',
        'detail' => 'new warning',
        'policyFindings' => [['check' => 'hours', 'verdict' => 'warn', 'key' => 'policy.outside_business_hours']],
    ], 409)]);

    try {
        Linqelio::messages()->send('c-1', MessageType::Text, ['text' => 'hi'], acknowledgedWarnings: []);
        $this->fail('expected a PolicyException');
    } catch (PolicyException $e) {
        expect($e->needsConfirmation())->toBeTrue()
            ->and($e->unacknowledgedWarnings())->toBe(['policy.outside_business_hours'])
            ->and($e->findings()[0]->check)->toBe('hours');
    }
});

it('reads a verdict newer than the package as deny', function (): void {
    expect(PolicyVerdict::parse('quarantine'))->toBe(PolicyVerdict::Deny)
        ->and(PolicyVerdict::parse(null))->toBe(PolicyVerdict::Deny)
        ->and(PolicyVerdict::Deny->isSendable())->toBeFalse();
});
