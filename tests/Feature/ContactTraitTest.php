<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Linqelio\Laravel\Concerns\HasLinqelioContact;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Jobs\SendMessage;

/**
 * A stand-in for a host application's model.
 */
final class Customer extends Model
{
    use HasLinqelioContact;

    protected $table = 'customers';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function (): void {
    Schema::create('customers', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->string('linqelio_contact_id')->nullable();
    });
});

it('refuses to send for a model that has no contact yet', function (): void {
    $customer = Customer::create(['name' => 'Ada']);

    // Silently doing nothing would be worse: the caller believes a customer was
    // told something.
    expect(fn () => $customer->sendMessage('hi'))
        ->toThrow(RuntimeException::class, 'has no linqelio_contact_id');
});

it('links a contact and remembers its id', function (): void {
    Http::fake(['*/contacts/create' => Http::response([
        'id' => 'c-99',
        'cabinetId' => 'cab-1',
        'identities' => [[
            'channelType' => 'wa_web',
            'providerId' => '380501082555',
            'phone' => '380501082555',
        ]],
    ], 201)]);

    $customer = Customer::create(['name' => 'Ada']);
    $contact = $customer->linkLinqelioContact(ChannelKind::WaWeb, phone: '+380501082555');

    $customer->refresh();

    expect($contact->id)->toBe('c-99')
        ->and($customer->linqelio_contact_id)->toBe('c-99')
        ->and($contact->phone())->toBe('380501082555');
});

it('queues a message rather than sending it inline', function (): void {
    Queue::fake();

    $customer = Customer::create(['name' => 'Ada', 'linqelio_contact_id' => 'c-99']);
    $customer->queueMessage('Thanks for your order');

    Queue::assertPushed(SendMessage::class, fn (SendMessage $job): bool => $job->contactId === 'c-99');
});
