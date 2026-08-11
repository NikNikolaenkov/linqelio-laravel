<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;

/**
 * Session tokens for the browser widget.
 *
 * A widget cannot hold your client key: it runs on a page your customer
 * controls. Instead the server mints a short-lived token scoped to one contact,
 * and the widget holds only that. Even if it leaks, it expires quickly and
 * reaches one person.
 *
 * Scoped to the CONTACT, and no further — see {@see self::session()} for what
 * that does and does not bound.
 */
final readonly class EmbedResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Mint a session token for a contact.
     *
     * `$capabilities` and `$conversationId` are NOT yet enforced. The platform
     * mints every token with its own default capability set and no conversation
     * scope, so a token asked for as read-only, or as confined to one thread, is
     * neither. Both arguments are sent and both are ignored; they stay so the
     * call site already says what it wants when the platform starts honouring it.
     *
     * Until then, assume the token can do what the widget's default UI can do,
     * for every conversation that contact has, until it expires.
     *
     * @param  array<int, string>  $capabilities  what the widget should be allowed
     *                                            to do — recorded, not yet enforced
     * @return array<string, mixed>
     */
    public function session(string $contactId, array $capabilities = [], ?string $conversationId = null): array
    {
        $body = array_filter([
            'contactId' => $contactId,
            'conversationId' => $conversationId,
            'cap' => $capabilities === [] ? null : array_values($capabilities),
        ], static fn ($v): bool => $v !== null);

        return $this->client->post('/embed/session', $body)->data;
    }
}
