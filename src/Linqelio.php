<?php

declare(strict_types=1);

namespace Linqelio\Laravel;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Resources\AlertsResource;
use Linqelio\Laravel\Resources\AnalyticsResource;
use Linqelio\Laravel\Resources\CampaignsResource;
use Linqelio\Laravel\Resources\ChannelsResource;
use Linqelio\Laravel\Resources\ContactExportsResource;
use Linqelio\Laravel\Resources\ContactImportsResource;
use Linqelio\Laravel\Resources\ContactsResource;
use Linqelio\Laravel\Resources\ConversationsResource;
use Linqelio\Laravel\Resources\EmbedResource;
use Linqelio\Laravel\Resources\GroupsResource;
use Linqelio\Laravel\Resources\HealthResource;
use Linqelio\Laravel\Resources\MediaResource;
use Linqelio\Laravel\Resources\MessagesResource;
use Linqelio\Laravel\Resources\ScheduledSendsResource;
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

    private ?GroupsResource $groups = null;

    private ?CampaignsResource $campaigns = null;

    private ?ScheduledSendsResource $scheduledSends = null;

    private ?HealthResource $health = null;

    private ?AlertsResource $alerts = null;

    private ?ContactImportsResource $contactImports = null;

    private ?ContactExportsResource $contactExports = null;

    private ?AnalyticsResource $analytics = null;

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

    public function groups(): GroupsResource
    {
        return $this->groups ??= new GroupsResource($this->client);
    }

    public function campaigns(): CampaignsResource
    {
        return $this->campaigns ??= new CampaignsResource($this->client);
    }

    public function scheduledSends(): ScheduledSendsResource
    {
        return $this->scheduledSends ??= new ScheduledSendsResource($this->client);
    }

    public function health(): HealthResource
    {
        return $this->health ??= new HealthResource($this->client);
    }

    public function alerts(): AlertsResource
    {
        return $this->alerts ??= new AlertsResource($this->client);
    }

    public function contactImports(): ContactImportsResource
    {
        return $this->contactImports ??= new ContactImportsResource($this->client);
    }

    public function contactExports(): ContactExportsResource
    {
        return $this->contactExports ??= new ContactExportsResource($this->client);
    }

    public function analytics(): AnalyticsResource
    {
        return $this->analytics ??= new AnalyticsResource($this->client);
    }

    /**
     * An entry point bound to another cabinet's key.
     *
     *     Linqelio::forKey($tenant->linqelio_key)->messages()->sendText(...);
     *
     * Needed by anything that serves more than one cabinet. The container holds
     * a single Linqelio built from `linqelio.key`, and it memoises its resources,
     * so this returns a fresh instance rather than mutating the shared one —
     * under a persistent worker, mutating it would leak the key into the next
     * request. See HttpClient::withKey().
     */
    public function forKey(string $key): self
    {
        return new self($this->client->withKey($key));
    }

    /** The transport, for calls this package has not wrapped yet. */
    public function client(): HttpClient
    {
        return $this->client;
    }
}
