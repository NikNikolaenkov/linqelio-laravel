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
 * Scope it as narrowly as the UI allows — {@see self::session()}.
 */
final readonly class EmbedResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Mint a session token for a contact.
     *
     * `$capabilities` is an UPPER BOUND: the issued token is never wider than
     * what you ask for. Omit it and the platform's default set is issued; name a
     * capability the platform does not issue and the call is refused rather than
     * quietly narrowed, so you are never left believing the token is smaller than
     * it is.
     *
     * `$conversationId` confines the token to one thread. Omitted, it reaches
     * every conversation that contact has.
     *
     * @param  array<int, string>  $capabilities  what the widget may do — keep it
     *                                            to the minimum the UI needs
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
