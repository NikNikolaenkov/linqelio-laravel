<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\ConsentSource;
use Linqelio\Laravel\Data\Enums\ConsentStatus;
use Linqelio\Laravel\Exceptions\ContactException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Consent as a host records it (issue #9, ADR-0084): per channel, with the key
 * as its author — which the platform records as `host_api`.
 */
function consentBody(string $status = 'granted'): array
{
    return [
        'channelId' => 'ch-1',
        'channelKind' => 'wa_web',
        'status' => $status,
        'source' => 'host_api',
        'grantedAt' => '2026-09-20T10:00:00Z',
        'grantedBy' => ['type' => 'api_key', 'id' => 'k-1'],
        'evidence' => 'crm-form-12',
        'updatedAt' => '2026-09-20T10:00:00Z',
    ];
}

it('lists a contact\'s consent per channel', function (): void {
    Http::fake(['*/contacts/c-1/consents' => Http::response(['items' => [consentBody(), consentBody('missing')]])]);

    $consents = Linqelio::contacts()->consents('c-1');

    expect($consents)->toHaveCount(2)
        ->and($consents[0]->isGranted())->toBeTrue()
        ->and($consents[0]->channelKind)->toBe(ChannelKind::WaWeb)
        ->and($consents[0]->source)->toBe(ConsentSource::HostApi)
        ->and($consents[0]->grantedBy?->isApiKey())->toBeTrue()
        ->and($consents[0]->evidence)->toBe('crm-form-12')
        ->and($consents[1]->status)->toBe(ConsentStatus::Missing);
});

it('grants consent with a PUT carrying only the evidence', function (): void {
    Http::fake(['*' => Http::response(['changed' => true, 'consent' => consentBody()])]);

    $change = Linqelio::contacts()->grantConsent('c-1', 'ch-1', 'crm-form-12');

    expect($change->changed)->toBeTrue()
        ->and($change->consent->isGranted())->toBeTrue();

    Http::assertSent(fn (Request $r): bool => $r->method() === 'PUT'
        && str_ends_with($r->url(), '/contacts/c-1/consents/ch-1')
        && $r->hasHeader('Idempotency-Key')
        && $r->data() === ['evidence' => 'crm-form-12']);
});

it('treats a repeated grant as a success that changed nothing', function (): void {
    Http::fake(['*' => Http::response(['changed' => false, 'consent' => consentBody()])]);

    expect(Linqelio::contacts()->grantConsent('c-1', 'ch-1')->changed)->toBeFalse();

    // No evidence given: nothing sent for it, rather than `evidence: null`.
    Http::assertSent(fn (Request $r): bool => ! array_key_exists('evidence', $r->data()));
});

it('revokes consent with a POST to the revoke action', function (): void {
    Http::fake(['*' => Http::response(['changed' => true, 'consent' => consentBody('revoked') + ['revokedAt' => '2026-09-21T10:00:00Z']])]);

    $change = Linqelio::contacts()->revokeConsent('c-1', 'ch-1');

    expect($change->consent->isRevoked())->toBeTrue()
        ->and($change->consent->revokedAt?->format('Y-m-d'))->toBe('2026-09-21');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/contacts/c-1/consents/ch-1/revoke'));
});

it('maps an unknown contact to a ContactException', function (): void {
    Http::fake(['*' => Http::response(['code' => 'contact.not_found', 'detail' => 'no such contact'], 404)]);

    expect(fn () => Linqelio::contacts()->consents('nope'))->toThrow(ContactException::class);
});
