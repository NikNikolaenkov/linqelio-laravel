<?php

declare(strict_types=1);

namespace Linqelio\Laravel;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Resources\ChannelsResource;
use Linqelio\Laravel\Resources\ContactsResource;
use Linqelio\Laravel\Resources\ConversationsResource;
use Linqelio\Laravel\Resources\EmbedResource;
use Linqelio\Laravel\Resources\MediaResource;
use Linqelio\Laravel\Resources\MessagesResource;
use Linqelio\Laravel\Resources\WebhooksResource;

/**
 * Entry point: `Linqelio::messages()->sendText(...)`.
 *
 * Resources are grouped the way the API is, and built lazily — a request that
 * only sends a message never constructs the rest.
 */
final class Linqelio
{
    private ?ChannelsResource $channels = null;

    private ?ContactsResource $contacts = null;

    private ?MessagesResource $messages = null;

    private ?MediaResource $media = null;

    private ?ConversationsResource $conversations = null;

    private ?EmbedResource $embed = null;

    private ?WebhooksResource $webhooks = null;

    public function __construct(private readonly HttpClient $client) {}

    public function channels(): ChannelsResource
    {
        return $this->channels ??= new ChannelsResource($this->client);
    }

    public function contacts(): ContactsResource
    {
        return $this->contacts ??= new ContactsResource($this->client);
    }

    public function messages(): MessagesResource
    {
        return $this->messages ??= new MessagesResource($this->client);
    }

    public function media(): MediaResource
    {
        return $this->media ??= new MediaResource($this->client);
    }

    public function conversations(): ConversationsResource
    {
        return $this->conversations ??= new ConversationsResource($this->client);
    }

    public function embed(): EmbedResource
    {
        return $this->embed ??= new EmbedResource($this->client);
    }

    public function webhooks(): WebhooksResource
    {
        return $this->webhooks ??= new WebhooksResource($this->client);
    }

    /** The transport, for calls this package has not wrapped yet. */
    public function client(): HttpClient
    {
        return $this->client;
    }
}
