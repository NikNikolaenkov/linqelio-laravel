<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Campaigns\CampaignAudience;
use Linqelio\Laravel\Data\Campaigns\CampaignContent;
use Linqelio\Laravel\Data\Campaigns\CampaignInput;
use Linqelio\Laravel\Data\Campaigns\CampaignTemplate;
use Linqelio\Laravel\Data\Campaigns\CampaignTemplateParam;
use Linqelio\Laravel\Data\Enums\CampaignParamSource;
use Linqelio\Laravel\Data\Enums\CampaignRecipientState;
use Linqelio\Laravel\Data\Enums\CampaignStatus;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Exceptions\CampaignException;
use Linqelio\Laravel\Facades\Linqelio;

function campaignBody(string $status = 'draft'): array
{
    return [
        'id' => 'cmp-1',
        'name' => 'Autumn',
        'status' => $status,
        'channels' => [['channelId' => 'ch-1', 'kind' => 'wa_cloud', 'position' => 0, 'live' => true]],
        'content' => ['type' => 'template', 'template' => ['name' => 'sale', 'language' => 'uk', 'params' => [['source' => 'contact_name', 'default' => 'friend']]]],
        'audience' => ['tags' => ['vip']],
        'timeZone' => 'Europe/Kyiv',
        'ratePerMinute' => 30,
        'maxAttempts' => 3,
        'progress' => ['total' => 10, 'queued' => 4, 'sent' => 5, 'failed' => 1],
        'createdBy' => ['type' => 'api_key', 'id' => 'k-1'],
        'createdAt' => '2026-09-24T10:00:00Z',
        'updatedAt' => '2026-09-24T11:00:00Z',
        'dryRunAt' => '2026-09-24T10:30:00Z',
    ];
}

it('creates a draft from a typed input and reads the campaign back', function (): void {
    Http::fake(['*' => Http::response(campaignBody(), 201)]);

    $campaign = Linqelio::campaigns()->create(new CampaignInput(
        name: 'Autumn',
        channelIds: ['ch-1'],
        content: CampaignContent::template(new CampaignTemplate('sale', 'uk', [CampaignTemplateParam::contactName('friend')])),
        audience: CampaignAudience::tagged(['vip']),
        startAt: new DateTimeImmutable('2026-10-01T09:00:00+03:00'),
    ));

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->channels[0]->kind)->toBe(ChannelKind::WaCloud)
        ->and($campaign->content->type)->toBe(MessageType::Template)
        ->and($campaign->content->template?->params[0]->source)->toBe(CampaignParamSource::ContactName)
        ->and($campaign->audience->tags)->toBe(['vip'])
        ->and($campaign->progress->pending())->toBe(4)
        ->and($campaign->needsDryRun())->toBeTrue();

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_ends_with($r->url(), '/campaigns')
        && $r->data() === [
            'name' => 'Autumn',
            'channelIds' => ['ch-1'],
            'content' => ['type' => 'template', 'template' => ['name' => 'sale', 'language' => 'uk', 'params' => [['source' => 'contact_name', 'default' => 'friend']]]],
            'audience' => ['tags' => ['vip']],
            'startAt' => '2026-10-01T09:00:00+03:00',
        ]);
});

it('pages campaigns and recipients with `since`', function (): void {
    Http::fake([
        '*/campaigns/cmp-1/recipients*' => Http::response(['items' => [['contactId' => 'c-1', 'state' => 'failed', 'code' => 'policy.consent_missing']], 'pageInfo' => ['nextCursor' => 'r-2']]),
        '*/campaigns*' => Http::response(['items' => [campaignBody('running')], 'pageInfo' => ['nextCursor' => 'cur-3']]),
    ]);

    $page = Linqelio::campaigns()->list(CampaignStatus::Running, 'cur-2', 10);
    $recipients = Linqelio::campaigns()->recipients('cmp-1', CampaignRecipientState::Failed, 'r-1');

    expect($page['campaigns'][0]->status)->toBe(CampaignStatus::Running)
        ->and($page['nextCursor'])->toBe('cur-3')
        ->and($recipients['recipients'][0]->code)->toBe('policy.consent_missing')
        ->and($recipients['nextCursor'])->toBe('r-2');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET' && ($r['since'] ?? null) === 'cur-2' && $r['status'] === 'running');
    Http::assertSent(fn (Request $r): bool => ($r['since'] ?? null) === 'r-1' && $r['state'] === 'failed');
});

it('drives the lifecycle through the contract\'s paths', function (Closure $call, string $method, string $path): void {
    Http::fake(['*' => Http::response(campaignBody('running'))]);

    $call();

    Http::assertSent(fn (Request $r): bool => $r->method() === $method && str_ends_with($r->url(), $path));
})->with([
    'find' => [fn () => Linqelio::campaigns()->find('cmp-1'), 'GET', '/campaigns/cmp-1'],
    'update' => [fn () => Linqelio::campaigns()->update('cmp-1', new CampaignInput('A', ['ch-1'], CampaignContent::text('hi'))), 'PUT', '/campaigns/cmp-1'],
    'audience' => [fn () => Linqelio::campaigns()->setAudience('cmp-1', CampaignAudience::everyone()), 'PUT', '/campaigns/cmp-1/audience'],
    'pause' => [fn () => Linqelio::campaigns()->pause('cmp-1'), 'POST', '/campaigns/cmp-1/pause'],
    'resume' => [fn () => Linqelio::campaigns()->resume('cmp-1'), 'POST', '/campaigns/cmp-1/resume'],
    'cancel' => [fn () => Linqelio::campaigns()->cancel('cmp-1'), 'POST', '/campaigns/cmp-1/cancel'],
    'delete' => [fn () => Linqelio::campaigns()->delete('cmp-1'), 'DELETE', '/campaigns/cmp-1'],
]);

it('sends `all` only when asked for by name', function (): void {
    expect(CampaignAudience::everyone()->toArray())->toBe(['all' => true])
        ->and((new CampaignAudience)->toArray())->toBe([])
        ->and(CampaignAudience::contacts(['c-1'])->toArray())->toBe(['contactIds' => ['c-1']]);
});

it('reads a deletion of nothing as a successful replay', function (): void {
    Http::fake(['*' => Http::response(['deleted' => false])]);

    expect(Linqelio::campaigns()->delete('gone'))->toBeFalse();
});

it('maps campaign refusals, with the reasons of an invalid draft', function (): void {
    Http::fake(['*' => Http::response([
        'code' => 'campaign.invalid',
        'detail' => 'not launchable',
        'errors' => [['field' => 'audience', 'reason' => 'empty']],
    ], 422)]);

    try {
        Linqelio::campaigns()->launch('cmp-1');
        $this->fail('expected a CampaignException');
    } catch (CampaignException $e) {
        expect($e->errors())->toBe(['audience' => 'empty']);
    }
});

it('sends a template campaign without content, and an empty audience as `[]` (issue #140)', function (): void {
    Http::fake(['*' => Http::response(['id' => 'cmp-1', 'status' => 'draft'], 201)]);

    Linqelio::campaigns()->create(new CampaignInput(
        name: 'Sale',
        channelIds: ['ch-1'],
        content: CampaignContent::template(new CampaignTemplate('sale', 'uk')),
        audience: new CampaignAudience,
    ));

    Http::assertSent(fn (Request $r): bool => $r['content'] === ['type' => 'template', 'template' => ['name' => 'sale', 'language' => 'uk']]
        && str_contains($r->body(), '"audience":[]'));
});

it('reads the deprecated template `variables` but never sends them', function (): void {
    $template = CampaignTemplate::fromArray(['name' => 'sale', 'variables' => ['1' => 'x'], 'params' => []]);

    expect($template->variables)->toBe(['1' => 'x'])
        ->and($template->toArray())->toBe(['name' => 'sale']);
});
