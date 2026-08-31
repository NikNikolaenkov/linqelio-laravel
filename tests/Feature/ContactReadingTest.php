<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * What a Contact tells you once you have one.
 *
 * The wrapper's job is not only to fetch the record but to answer the questions
 * a host actually asks of it: what do I call this person, and are they already
 * somebody in my own database. Both were answerable from the payload and
 * unanswerable from the object.
 */
it('reads the link back to your own record', function (): void {
    Http::fake(['*/contacts/c-1' => Http::response([
        'id' => 'c-1',
        'cabinetId' => 'cab-1',
        'identities' => [],
        'profile' => ['displayName' => 'Ada'],
        'hostRefs' => [
            ['system' => 'crm', 'externalId' => '4711'],
            ['system' => 'billing', 'externalId' => 'INV-9'],
        ],
        '_meta' => ['version' => 3],
    ])]);

    $contact = Linqelio::contacts()->find('c-1');

    // Writable through update() and, until now, unreadable here — so the one
    // question the link exists to answer could not be asked of the result.
    expect($contact->hostRef('crm'))->toBe('4711')
        ->and($contact->hostRef('billing'))->toBe('INV-9')
        ->and($contact->hostRef('warehouse'))->toBeNull();
});

it('falls back to the handle when nobody has a name', function (): void {
    Http::fake(['*/contacts/c-2' => Http::response([
        'id' => 'c-2',
        'cabinetId' => 'cab-1',
        'identities' => [
            ['channelType' => 'tg_bot', 'providerId' => '8661226962', 'username' => 'ada_l'],
        ],
        'profile' => [],
        '_meta' => ['version' => 1],
    ])]);

    // A Telegram account often carries nothing but a handle. Stopping at push
    // name read it as anonymous, while the platform — whose own fallback goes on
    // to the username — showed a name for the same person.
    expect(Linqelio::contacts()->find('c-2')->displayName())->toBe('ada_l');
});

it('prefers a real name over a handle', function (): void {
    Http::fake(['*/contacts/c-3' => Http::response([
        'id' => 'c-3',
        'cabinetId' => 'cab-1',
        'identities' => [
            ['channelType' => 'wa_web', 'providerId' => '380501112233', 'pushName' => 'Ада', 'username' => 'ada_l'],
        ],
        'profile' => [],
        '_meta' => ['version' => 1],
    ])]);

    expect(Linqelio::contacts()->find('c-3')->displayName())->toBe('Ада');
});
