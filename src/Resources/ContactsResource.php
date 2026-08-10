<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Contact;
use Linqelio\Laravel\Data\Enums\ChannelKind;

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
            'cursor' => $cursor,
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
     * Pass the `version` you last read to make a concurrent edit fail with
     * `contact.version_conflict` instead of overwriting somebody else's change.
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
     * @return array<string, mixed>
     */
    public function invite(string $id, string $channelId): array
    {
        return $this->client->post("/contacts/{$id}/invite", ['channelId' => $channelId])->data;
    }
}
