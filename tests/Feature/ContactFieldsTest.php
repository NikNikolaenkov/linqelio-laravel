<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\ContactFieldOwner;
use Linqelio\Laravel\Data\Enums\ContactFieldType;
use Linqelio\Laravel\Data\Enums\FieldValueSource;
use Linqelio\Laravel\Exceptions\ContactException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * Typed contact fields (issue #9, ADR-0061 §10): written by a host as `host`,
 * never over a value a person wrote.
 */
it('reads field values with their provenance', function (): void {
    Http::fake(['*/contacts/c-1/fields' => Http::response([
        'fields' => ['tier' => 'gold', 'orders' => 12],
        'provenance' => [
            'tier' => ['source' => 'host', 'at' => '2026-09-20T10:00:00Z', 'by' => 'k-1'],
            'orders' => ['source' => 'human', 'at' => '2026-09-21T10:00:00Z'],
        ],
    ])]);

    $values = Linqelio::contacts()->fields('c-1');

    expect($values->get('tier'))->toBe('gold')
        ->and($values->get('orders'))->toBe(12)
        ->and($values->get('missing', 'x'))->toBe('x')
        ->and($values->isFromHost('tier'))->toBeTrue()
        ->and($values->origin('orders')?->source)->toBe(FieldValueSource::Human);
});

it('writes fields with a PATCH, null clearing one', function (): void {
    Http::fake(['*' => Http::response([
        'fields' => ['tier' => 'gold'],
        'provenance' => ['tier' => ['source' => 'host', 'at' => '2026-09-20T10:00:00Z']],
        'applied' => ['tier'],
        'cleared' => ['old'],
        'kept' => [['field' => 'phone', 'reason' => 'lower_authority', 'held' => 'human', 'offered' => 'host']],
    ])]);

    $result = Linqelio::contacts()->setFields('c-1', ['tier' => 'gold', 'old' => null, 'phone' => '+380']);

    expect($result->applied)->toBe(['tier'])
        ->and($result->cleared)->toBe(['old'])
        ->and($result->isComplete())->toBeFalse()
        ->and($result->kept[0]->field)->toBe('phone')
        ->and($result->kept[0]->held)->toBe(FieldValueSource::Human)
        ->and($result->values()->get('tier'))->toBe('gold');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'PATCH'
        && str_ends_with($r->url(), '/contacts/c-1/fields')
        && $r->data() === ['fields' => ['tier' => 'gold', 'old' => null, 'phone' => '+380']]);
});

it('names every invalid field of a refused write', function (): void {
    Http::fake(['*' => Http::response([
        'code' => 'contact.field_invalid',
        'detail' => 'invalid fields',
        'errors' => [['field' => 'orders', 'reason' => 'type_mismatch']],
    ], 422)]);

    try {
        Linqelio::contacts()->setFields('c-1', ['orders' => 'many']);
        $this->fail('expected a ContactException');
    } catch (ContactException $e) {
        expect($e->errors())->toBe(['orders' => 'type_mismatch']);
    }
});

it('reads the cabinet\'s field schema', function (): void {
    Http::fake(['*/contact-fields' => Http::response(['items' => [[
        'key' => 'tier',
        'label' => ['uk' => 'Рівень', 'en' => 'Tier'],
        'type' => 'enum',
        'options' => [['value' => 'gold', 'label' => ['en' => 'Gold']], ['value' => 'silver']],
        'owner' => 'host',
        'aiExtract' => false,
        'aiHint' => '',
        'syncToDesk' => true,
        'position' => 2,
    ]]])]);

    $definition = Linqelio::contacts()->fieldDefinitions()[0];

    expect($definition->type)->toBe(ContactFieldType::Enum)
        ->and($definition->owner)->toBe(ContactFieldOwner::Host)
        ->and($definition->allowedValues())->toBe(['gold', 'silver'])
        ->and($definition->labelIn('en'))->toBe('Tier')
        ->and($definition->labelIn('de'))->toBe('Рівень')
        ->and($definition->syncToDesk)->toBeTrue();

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET' && str_ends_with($r->url(), '/contact-fields'));
});
