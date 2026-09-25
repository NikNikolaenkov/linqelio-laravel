<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Ai\AiProfileFill;
use Linqelio\Laravel\Data\Ai\ContactAiProfile;
use Linqelio\Laravel\Data\Consent\ConsentChange;
use Linqelio\Laravel\Data\Consent\ContactConsent;
use Linqelio\Laravel\Data\Contact;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\ErasureResult;
use Linqelio\Laravel\Data\Fields\ContactFieldDefinition;
use Linqelio\Laravel\Data\Fields\ContactFieldsUpdate;
use Linqelio\Laravel\Data\Fields\ContactFieldValues;
use Linqelio\Laravel\Data\Read;
use Linqelio\Laravel\Exceptions\AiException;
use Linqelio\Laravel\Exceptions\ContactException;

final readonly class ContactsResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * @param  string|null  $q  substring search over display name and identities
     *                          (phone / username / provider id); digits also match
     *                          phones written with separators
     * @return array{contacts: array<int, Contact>, nextCursor: ?string}
     */
    public function list(
        ?string $cursor = null,
        ?int $limit = null,
        ?string $status = null,
        ?string $q = null,
    ): array {
        $response = $this->client->get('/contacts', array_filter([
            // `since` on the wire — the contract's forward cursor. The argument
            // keeps the neutral name because what you pass is always just the
            // previous page's `pageInfo.nextCursor`.
            'since' => $cursor,
            'limit' => $limit,
            'status' => $status,
            'q' => $q,
        ], static fn ($v): bool => $v !== null));

        return [
            'contacts' => array_map(Contact::fromArray(...), $response->items()),
            'nextCursor' => $response->nextCursor(),
        ];
    }

    public function find(string $id): Contact
    {
        return Contact::fromArray($this->client->get("/contacts/{$id}")->data);
    }

    /**
     * Find the contact reachable at a given channel address.
     *
     * Webhooks identify the other party by `contactRef` — the provider's own id
     * for them — not by our contact id, so this is the bridge between the two.
     *
     * The search endpoint matches substrings across names and identities, so the
     * result is filtered down to an exact identity match here: a `q` of "380500"
     * would otherwise happily return somebody whose number merely contains it.
     */
    public function findByIdentity(string $providerId, ?string $channelType = null): ?Contact
    {
        if ($providerId === '') {
            return null;
        }

        foreach ($this->list(limit: 25, q: $providerId)['contacts'] as $contact) {
            foreach ($contact->identities as $identity) {
                $matches = $identity->providerId === $providerId
                    && ($channelType === null || $identity->channelType === $channelType);

                if ($matches) {
                    return $contact;
                }
            }
        }

        return null;
    }

    /**
     * Register somebody the cabinet has not messaged yet.
     *
     * Idempotent on the identity, not on the call: creating the same
     * channel + address twice returns the existing contact rather than a
     * duplicate. `providerId` defaults to the phone, which is how WhatsApp and
     * Viber address people; Telegram accounts get re-keyed to their numeric id
     * once the messenger resolves the number.
     *
     * On an address that already exists the attributes you pass are refreshed
     * and the ones you omit are left alone. Because this call doubles as the
     * address resolve people run before sending — where all you have is the
     * `providerId` — omitting a field has to mean "I did not look", never "it is
     * gone". Do not pass an empty string expecting it to clear anything.
     */
    public function create(
        ChannelKind $channelType,
        ?string $phone = null,
        ?string $username = null,
        ?string $providerId = null,
        ?string $name = null,
    ): Contact {
        $body = array_filter([
            'channelType' => $channelType->value,
            'phone' => $phone,
            'username' => $username,
            'providerId' => $providerId,
            'name' => $name,
        ], static fn ($v): bool => $v !== null);

        return Contact::fromArray($this->client->post('/contacts/create', $body)->data);
    }

    /**
     * Update the fields your side owns.
     *
     * Pass the `version` you last read and the write applies only if nothing
     * changed underneath; otherwise it fails with `contact.version_conflict` and
     * you refetch and retry. Omit it and the update is last-writer-wins.
     *
     * Only `custom` is guarded. `hostRefs` are links rather than values, and
     * adding one twice is not a conflict.
     *
     * Messenger-owned fields (phone, push name, avatar) are read-only — they are
     * projections of the channel and are refreshed from inbound traffic.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $id, array $attributes, ?int $version = null): Contact
    {
        if ($version !== null) {
            $attributes['_meta'] = ['version' => $version];
        }

        return Contact::fromArray($this->client->patch("/contacts/{$id}", $attributes)->data);
    }

    /**
     * Mint a deep link that starts a bot conversation with this contact.
     *
     * Only channels that support click-to-chat can do this (Telegram and Viber
     * bots); asking a paired WhatsApp channel yields
     * `channel.capability_unsupported`.
     *
     * Pin `$idempotencyKey` to make a retry replay the first link rather than
     * mint a second one (ADR-0103).
     *
     * @return array<string, mixed>
     */
    public function invite(string $id, string $channelId, ?string $idempotencyKey = null): array
    {
        return $this->client->post(
            "/contacts/{$id}/invite",
            ['channelId' => $channelId],
            idempotencyKey: $idempotencyKey,
        )->data;
    }

    /**
     * Erase a person: the contact, and every trace of them in the records that
     * outlive it — message bodies and metadata, chat ids, contact references.
     *
     * Not a delete, because deleting the contact would not be enough. Messages
     * carry the person's number in their own columns and have no link back to a
     * contact to cascade through, so the platform redacts them instead, in one
     * transaction.
     *
     * Answer a deletion request with this, then remove whatever copies you hold
     * yourself — this call cannot reach those, including the message projection
     * this package writes into your own database.
     *
     * IRREVERSIBLE. Idempotent: erasing someone already erased returns zero
     * counts instead of failing, so a retry after a timeout is safe.
     */
    public function erase(string $id): ErasureResult
    {
        return ErasureResult::fromArray($this->client->post("/contacts/{$id}/erase")->data);
    }

    /**
     * The contact's consent on every channel of the cabinet.
     *
     * @return array<int, ContactConsent>
     */
    public function consents(string $id): array
    {
        return array_map(ContactConsent::fromArray(...), $this->client->get("/contacts/{$id}/consents")->items());
    }

    /**
     * Record that the contact agreed to be messaged on one channel.
     *
     * The source is not yours to choose — it is who calls: through an API key it
     * is recorded as `host_api`, with the key as its author. `$evidence` is a
     * short reference to where the consent came from (a form id, your CRM
     * record), never personal data: it is kept with the consent and in the
     * audit log.
     *
     * Idempotent. An active consent is left as it is (`changed: false` — the
     * first grant's provenance is the earliest evidence). A REVOKED one is
     * re-granted: an explicit grant lifts an earlier opt-out, so only call this
     * when the person actually said yes again.
     */
    public function grantConsent(string $id, string $channelId, ?string $evidence = null): ConsentChange
    {
        $response = $this->client->put(
            "/contacts/{$id}/consents/{$channelId}",
            Read::compact(['evidence' => $evidence]),
        );

        return ConsentChange::fromArray($response->data);
    }

    /**
     * Record that the contact withdrew consent on one channel. From then on a
     * send there is refused with `policy.consent_missing` while the cabinet
     * enforces consent.
     *
     * Holds even when nothing had been granted — "do not message me" also stops
     * a later automatic grant from an inbound message. Idempotent: revoking a
     * revoked consent answers `changed: false` and keeps the first moment.
     */
    public function revokeConsent(string $id, string $channelId): ConsentChange
    {
        return ConsentChange::fromArray(
            $this->client->post("/contacts/{$id}/consents/{$channelId}/revoke")->data,
        );
    }

    /** The contact's typed field values, each with who wrote it. */
    public function fields(string $id): ContactFieldValues
    {
        return ContactFieldValues::fromArray($this->client->get("/contacts/{$id}/fields")->data);
    }

    /**
     * Set typed field values; `null` clears one.
     *
     * Written as `host`. All-or-nothing on VALIDITY: if any value does not fit
     * the schema nothing is written and the call fails with
     * `contact.field_invalid` ({@see ContactException::errors()} names each
     * field). But a value a person wrote is not overwritten and that is NOT an
     * error — check {@see ContactFieldsUpdate::$kept}.
     *
     * @param  array<string, mixed>  $fields  field key => value (or null to clear)
     */
    public function setFields(string $id, array $fields): ContactFieldsUpdate
    {
        // An empty map goes as `[]`; the platform reads it as `{}` (issue #140)
        // — writes nothing and answers the current values.
        $body = ['fields' => $fields];

        return ContactFieldsUpdate::fromArray($this->client->patch("/contacts/{$id}/fields", $body)->data);
    }

    /**
     * The cabinet's typed field schema — what {@see self::setFields()} accepts.
     * Edited in the console, not here.
     *
     * @return array<int, ContactFieldDefinition>
     */
    public function fieldDefinitions(): array
    {
        return array_map(ContactFieldDefinition::fromArray(...), $this->client->get('/contact-fields')->items());
    }

    /** The contact's AI questionnaire, AI summary and last run. */
    public function aiProfile(string $id): ContactAiProfile
    {
        return ContactAiProfile::fromArray($this->client->get("/contacts/{$id}/ai-profile")->data);
    }

    /**
     * Run the AI questionnaire now over the contact's latest conversation.
     *
     * Queued, not immediate: read the result later with {@see self::aiProfile()}.
     * Idempotent per conversation and newest message — asking again before
     * anything new was said returns the same run. Fails with `ai.disabled`
     * ({@see AiException}) when the cabinet has not consented to AI processing.
     */
    public function fillAiProfile(string $id, ?string $idempotencyKey = null): AiProfileFill
    {
        return AiProfileFill::fromArray(
            $this->client->post("/contacts/{$id}/ai-profile/fill", idempotencyKey: $idempotencyKey)->data,
        );
    }
}
