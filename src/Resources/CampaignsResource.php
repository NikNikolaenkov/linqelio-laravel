<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Campaigns\AudiencePreview;
use Linqelio\Laravel\Data\Campaigns\Campaign;
use Linqelio\Laravel\Data\Campaigns\CampaignAudience;
use Linqelio\Laravel\Data\Campaigns\CampaignDryRun;
use Linqelio\Laravel\Data\Campaigns\CampaignInput;
use Linqelio\Laravel\Data\Campaigns\CampaignLaunch;
use Linqelio\Laravel\Data\Campaigns\CampaignRecipient;
use Linqelio\Laravel\Data\Enums\CampaignRecipientState;
use Linqelio\Laravel\Data\Enums\CampaignStatus;
use Linqelio\Laravel\Data\Read;
use Linqelio\Laravel\Exceptions\CampaignException;

/**
 * Campaigns: one message to an audience, sent through send policy in its
 * campaign mode — a limit reschedules a recipient instead of failing it, and a
 * recipient without consent on the channel is skipped.
 *
 * The lifecycle is draft → dry run → launch → running (pause / resume) →
 * completed, or cancelled. Launch refuses a draft without a dry run newer than
 * its last change (`campaign.dry_run_required`), so the order is not optional:
 *
 *     $draft = Linqelio::campaigns()->create($input);
 *     $run = Linqelio::campaigns()->dryRun($draft->id);
 *     if ($run->launchable) { Linqelio::campaigns()->launch($draft->id); }
 *
 * Needs campaigns:manage.
 */
final readonly class CampaignsResource
{
    public function __construct(private HttpClient $client) {}

    public function create(CampaignInput $input): Campaign
    {
        return Campaign::fromArray($this->client->post('/campaigns', $input->toArray())->data);
    }

    /**
     * @return array{campaigns: array<int, Campaign>, nextCursor: ?string}
     */
    public function list(?CampaignStatus $status = null, ?string $cursor = null, ?int $limit = null): array
    {
        $response = $this->client->get('/campaigns', Read::compact([
            'status' => $status?->value,
            'since' => $cursor,
            'limit' => $limit,
        ]));

        return [
            'campaigns' => array_map(Campaign::fromArray(...), $response->items()),
            'nextCursor' => $response->nextCursor(),
        ];
    }

    public function find(string $campaignId): Campaign
    {
        return Campaign::fromArray($this->client->get("/campaigns/{$campaignId}")->data);
    }

    /**
     * Replace a DRAFT — all of it: name, channels, message, audience, schedule.
     * Anything past a draft answers `campaign.state_conflict`.
     */
    public function update(string $campaignId, CampaignInput $input): Campaign
    {
        return Campaign::fromArray($this->client->put("/campaigns/{$campaignId}", $input->toArray())->data);
    }

    /**
     * Delete a draft or a finished campaign, with its recipient list. A live one
     * (scheduled, running, paused) has to be cancelled first. Messages already
     * sent stay in their conversations.
     *
     * @return bool false when the id named nothing — a successful replay, not an error
     */
    public function delete(string $campaignId): bool
    {
        return Read::bool($this->client->delete("/campaigns/{$campaignId}")->data, 'deleted');
    }

    /** Replace a draft's audience only. */
    public function setAudience(string $campaignId, CampaignAudience $audience): Campaign
    {
        return Campaign::fromArray(
            $this->client->put("/campaigns/{$campaignId}/audience", $audience->toArray())->data,
        );
    }

    /**
     * Rehearse a draft: who would receive it, who would be skipped and why, how
     * long it would take. Sends nothing, consumes nothing, and is what launch
     * requires.
     */
    public function dryRun(string $campaignId): CampaignDryRun
    {
        return CampaignDryRun::fromArray($this->client->post("/campaigns/{$campaignId}/dry-run")->data);
    }

    /**
     * Count who an audience would reach on the given channels, before any
     * campaign exists.
     *
     * @param  array<int, string>  $channelIds
     */
    public function previewAudience(array $channelIds, CampaignAudience $audience): AudiencePreview
    {
        $response = $this->client->post('/campaigns/audience-preview', [
            'channelIds' => array_values($channelIds),
            // The audience is an OBJECT in the contract, even when empty.
            'audience' => $audience->toArray() === [] ? new \stdClass : $audience->toArray(),
        ]);

        return AudiencePreview::fromArray($response->data);
    }

    /**
     * Resolve the audience into the recipient list — once; nobody is added later
     * — and schedule the campaign, or start it when it has no future `startAt`.
     * An invalid draft answers `campaign.invalid` with one `errors[]` entry per
     * reason ({@see CampaignException::errors()}).
     */
    public function launch(string $campaignId): CampaignLaunch
    {
        return CampaignLaunch::fromArray($this->client->post("/campaigns/{$campaignId}/launch")->data);
    }

    public function pause(string $campaignId): Campaign
    {
        return Campaign::fromArray($this->client->post("/campaigns/{$campaignId}/pause")->data);
    }

    public function resume(string $campaignId): Campaign
    {
        return Campaign::fromArray($this->client->post("/campaigns/{$campaignId}/resume")->data);
    }

    /** Stop a scheduled, running or paused campaign for good. */
    public function cancel(string $campaignId): Campaign
    {
        return Campaign::fromArray($this->client->post("/campaigns/{$campaignId}/cancel")->data);
    }

    /**
     * A launched campaign's recipients and where each one is.
     *
     * @return array{recipients: array<int, CampaignRecipient>, nextCursor: ?string}
     */
    public function recipients(
        string $campaignId,
        ?CampaignRecipientState $state = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): array {
        $response = $this->client->get("/campaigns/{$campaignId}/recipients", Read::compact([
            'state' => $state?->value,
            'since' => $cursor,
            'limit' => $limit,
        ]));

        return [
            'recipients' => array_map(CampaignRecipient::fromArray(...), $response->items()),
            'nextCursor' => $response->nextCursor(),
        ];
    }
}
