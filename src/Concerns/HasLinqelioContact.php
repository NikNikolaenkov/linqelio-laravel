<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Linqelio\Laravel\Data\Contact;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\Message;
use Linqelio\Laravel\Facades\Linqelio;
use Linqelio\Laravel\Jobs\SendMessage;
use Linqelio\Laravel\Models\LinqelioMessage;

/**
 * Gives one of your models a conversation.
 *
 * Add a nullable `linqelio_contact_id` string column and use this trait:
 *
 *   class Customer extends Model { use HasLinqelioContact; }
 *   $customer->sendMessage('Your order has shipped');
 *
 * One column, no synchronisation. The contact itself — who they are, which
 * channels they are reachable on, whether two addresses are the same person —
 * stays on the platform, which is the only place that can answer those
 * questions correctly.
 */
trait HasLinqelioContact
{
    public function linqelioContactId(): ?string
    {
        $id = $this->getAttribute($this->linqelioContactIdColumn());

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** The contact record, fetched from the API. */
    public function linqelioContact(): ?Contact
    {
        $id = $this->linqelioContactId();

        return $id === null ? null : Linqelio::contacts()->find($id);
    }

    /**
     * Attach this model to a contact, creating one if the address is new.
     *
     * Idempotent on the identity: calling it twice with the same number returns
     * the same contact rather than a second one.
     */
    public function linkLinqelioContact(
        ChannelKind $channelType,
        ?string $phone = null,
        ?string $username = null,
    ): Contact {
        $contact = Linqelio::contacts()->create(
            channelType: $channelType,
            phone: $phone,
            username: $username,
            name: $this->linqelioDisplayName(),
        );

        $this->setAttribute($this->linqelioContactIdColumn(), $contact->id);
        $this->save();

        return $contact;
    }

    /**
     * Send now, and get the queued message back.
     *
     * Throws if this model has no contact yet — silently doing nothing would be
     * worse, because the caller believes a customer was told something.
     */
    public function sendMessage(
        string $text,
        ?string $channelId = null,
        ?string $idempotencyKey = null,
    ): Message {
        return Linqelio::messages()->sendText(
            contactId: $this->requireLinqelioContactId(),
            text: $text,
            channelId: $channelId,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Send from a queue — the better default for anything triggered by a request,
     * since delivery should not be able to slow a checkout down or fail it.
     */
    public function queueMessage(
        string $text,
        ?string $channelId = null,
        ?string $idempotencyKey = null,
    ): void {
        SendMessage::dispatch(
            $this->requireLinqelioContactId(),
            MessageType::Text,
            ['text' => $text],
            $channelId,
            $idempotencyKey,
        );
    }

    /**
     * The locally projected conversation, if the projection is enabled.
     *
     * @return HasMany<LinqelioMessage, $this>
     */
    public function linqelioMessages(): HasMany
    {
        return $this->hasMany(LinqelioMessage::class, 'contact_id', $this->linqelioContactIdColumn());
    }

    /** Override to use a different column. */
    protected function linqelioContactIdColumn(): string
    {
        return 'linqelio_contact_id';
    }

    /** Override to give new contacts a nicer name than the model's key. */
    protected function linqelioDisplayName(): ?string
    {
        $name = $this->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function requireLinqelioContactId(): string
    {
        $id = $this->linqelioContactId();

        if ($id === null) {
            throw new \RuntimeException(sprintf(
                '%s #%s has no linqelio_contact_id. Call linkLinqelioContact() first — '.
                'a message cannot be addressed without knowing who it is for.',
                static::class,
                (string) $this->getKey(),
            ));
        }

        return $id;
    }
}
