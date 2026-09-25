<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Campaigns\CampaignAudience;
use Linqelio\Laravel\Data\Enums\CampaignStatus;
use Linqelio\Laravel\Data\Enums\ErrorCode;
use Linqelio\Laravel\Data\Enums\PolicyVerdict;
use Linqelio\Laravel\Exceptions\CampaignException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * The dry run is what launch requires, so reading it right is the difference
 * between a campaign that goes and one that is refused at the last step.
 */
it('reads a dry run: audience, blockers, channels, estimate and samples', function (): void {
    Http::fake(['*/campaigns/cmp-1/dry-run' => Http::response([
        'campaignId' => 'cmp-1',
        'evaluatedAt' => '2026-09-24T10:00:00Z',
        'launchable' => false,
        'problems' => [['field' => 'startAt', 'reason' => 'in the past']],
        'audience' => ['matched' => 120, 'explicitRequested' => 5, 'explicitIncluded' => 4, 'tooLarge' => false, 'limit' => 100000],
        'reachable' => 100,
        'blocked' => [['reason' => 'no_consent', 'code' => 'policy.consent_missing', 'count' => 15], ['reason' => 'no_address', 'code' => 'x', 'count' => 5]],
        'channels' => [['channelId' => 'ch-1', 'kind' => 'wa_web', 'position' => 0, 'live' => true, 'addressable' => 110, 'assigned' => 100, 'blocked' => 10, 'perMinute' => 2.5, 'paceLimitedBy' => 'warmup', 'dailyLimit' => 200]],
        'estimate' => ['startsAt' => '2026-09-24T10:00:00Z', 'durationSeconds' => 2400, 'complete' => true, 'limitingFactor' => 'warmup', 'factors' => [['factor' => 'warmup', 'seconds' => 2400]]],
        'samples' => [['channelId' => 'ch-1', 'contactId' => 'c-1', 'verdict' => 'allow', 'findings' => [], 'meters' => []]],
        'warnings' => [['key' => 'window_short', 'params' => ['hours' => '2']]],
    ])]);

    $run = Linqelio::campaigns()->dryRun('cmp-1');

    expect($run->launchable)->toBeFalse()
        ->and($run->problems)->toBe(['startAt' => 'in the past'])
        ->and($run->audience->matched)->toBe(120)
        ->and($run->audience->explicitIncluded)->toBe(4)
        ->and($run->blockedCount())->toBe(20)
        ->and($run->channels[0]->perMinute)->toBe(2.5)
        ->and($run->channels[0]->dailyLimit)->toBe(200)
        ->and($run->estimate->factors)->toBe(['warmup' => 2400])
        ->and($run->samples[0]->verdict)->toBe(PolicyVerdict::Allow)
        ->and($run->warnings)->toBe(['window_short' => ['hours' => '2']]);

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && str_ends_with($r->url(), '/campaigns/cmp-1/dry-run'));
});

it('previews an audience on channels before a campaign exists', function (): void {
    Http::fake(['*' => Http::response([
        'audience' => ['matched' => 3, 'explicitRequested' => 0, 'explicitIncluded' => 0, 'tooLarge' => false, 'limit' => 100000],
        'channels' => [['channelId' => 'ch-1', 'kind' => 'tg_bot', 'addressable' => 2]],
    ])]);

    $preview = Linqelio::campaigns()->previewAudience(['ch-1'], CampaignAudience::tagged(['vip']));

    expect($preview->audience->matched)->toBe(3)
        ->and($preview->addressable)->toBe(['ch-1' => 2]);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/campaigns/audience-preview')
        && $r->data() === ['channelIds' => ['ch-1'], 'audience' => ['tags' => ['vip']]]);
});

it('launches and reads the snapshot it took', function (): void {
    Http::fake(['*' => Http::response([
        'campaign' => ['id' => 'cmp-1', 'status' => 'running'],
        'recipients' => 96,
        'explicitRequested' => 5,
        'explicitIncluded' => 4,
    ])]);

    $launch = Linqelio::campaigns()->launch('cmp-1');

    expect($launch->campaign->status)->toBe(CampaignStatus::Running)
        ->and($launch->recipients)->toBe(96)
        ->and($launch->explicitIncluded)->toBe(4);
});

it('refuses a launch without a fresh dry run as a CampaignException', function (): void {
    Http::fake(['*' => Http::response(['code' => 'campaign.dry_run_required', 'detail' => 'dry run first'], 409)]);

    try {
        Linqelio::campaigns()->launch('cmp-1');
        $this->fail('expected a CampaignException');
    } catch (CampaignException $e) {
        expect($e->errorCode())->toBe(ErrorCode::CampaignDryRunRequired)
            ->and($e->status())->toBe(409);
    }
});

it('previews an empty audience as `[]`, which the platform reads as `{}` (issue #140)', function (): void {
    Http::fake(['*' => Http::response(['audience' => ['matched' => 0]])]);

    Linqelio::campaigns()->previewAudience(['ch-1'], new CampaignAudience);

    Http::assertSent(fn (Request $r): bool => str_contains($r->body(), '"audience":[]'));
});
