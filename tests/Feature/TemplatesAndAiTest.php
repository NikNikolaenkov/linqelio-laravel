<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\AiRunStatus;
use Linqelio\Laravel\Data\Enums\FieldValueSource;
use Linqelio\Laravel\Exceptions\AiException;
use Linqelio\Laravel\Exceptions\TemplateException;
use Linqelio\Laravel\Facades\Linqelio;

it('lists a channel\'s templates with the state of the last sync', function (): void {
    Http::fake(['*/channels/ch-1/templates' => Http::response([
        'items' => [[
            'id' => 't-1', 'channelId' => 'ch-1', 'name' => 'order_update', 'language' => 'uk',
            'category' => 'UTILITY', 'status' => 'APPROVED', 'parameterFormat' => 'POSITIONAL', 'sendable' => true,
            'body' => 'Order {{1}} for {{2}}', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'OK']],
            'parameters' => [['component' => 'body', 'index' => 1, 'example' => 'A-17'], ['component' => 'body', 'index' => 2]],
            'syncedAt' => '2026-09-24T09:00:00Z',
        ]],
        'sync' => ['lastSyncedAt' => '2026-09-24T09:00:00Z', 'templateCount' => 1],
    ])]);

    $list = Linqelio::channels()->templates('ch-1');
    $template = $list->find('order_update', 'uk');

    expect($list->templateCount)->toBe(1)
        ->and($list->lastErrorCode)->toBeNull()
        ->and($template?->sendable)->toBeTrue()
        ->and($template?->parameters)->toHaveCount(2)
        ->and($template?->buttons[0]['text'])->toBe('OK')
        ->and($template?->toSend(['A-17', 'Olena'])->toArray())
        ->toBe(['name' => 'order_update', 'language' => 'uk', 'params' => ['A-17', 'Olena']])
        ->and($list->find('order_update', 'en'))->toBeNull();
});

it('syncs templates from Meta on demand', function (): void {
    Http::fake(['*' => Http::response(['added' => 1, 'updated' => 2, 'removed' => 0, 'unchanged' => 5, 'total' => 8, 'syncedAt' => '2026-09-24T10:00:00Z'])]);

    $result = Linqelio::channels()->syncTemplates('ch-1');

    expect($result->added)->toBe(1)->and($result->total)->toBe(8);

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && str_ends_with($r->url(), '/channels/ch-1/templates/sync'));
});

it('maps a template the channel does not know to a TemplateException', function (): void {
    Http::fake(['*' => Http::response(['code' => 'template.not_found', 'detail' => 'sync first'], 404)]);

    expect(fn () => Linqelio::channels()->syncTemplates('ch-1'))->toThrow(TemplateException::class);
});

it('reads the AI questionnaire and queues a fill', function (): void {
    Http::fake([
        '*/ai-profile/fill' => Http::response(['runId' => 'run-1', 'created' => false, 'conversationId' => 'cv-1'], 202),
        '*/ai-profile' => Http::response([
            'enabled' => true,
            'fields' => [['key' => 'budget', 'filled' => false], ['key' => 'city', 'filled' => true, 'source' => 'human']],
            'summaryField' => 'summary',
            'summary' => 'Wants a quote',
            'summaryOrigin' => ['source' => 'ai', 'at' => '2026-09-24T09:00:00Z'],
            'lastRun' => ['id' => 'run-0', 'status' => 'succeeded', 'createdAt' => '2026-09-24T09:00:00Z', 'applied' => ['summary'], 'kept' => ['city']],
        ]),
    ]);

    $profile = Linqelio::contacts()->aiProfile('c-1');
    $fill = Linqelio::contacts()->fillAiProfile('c-1');

    expect($profile->unfilled())->toBe(['budget'])
        ->and($profile->fields[1]['source'])->toBe(FieldValueSource::Human)
        ->and($profile->summaryOrigin?->source)->toBe(FieldValueSource::Ai)
        ->and($profile->lastRun['status'] ?? null)->toBe(AiRunStatus::Succeeded)
        ->and($fill->created)->toBeFalse()
        ->and($fill->runId)->toBe('run-1');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && str_ends_with($r->url(), '/contacts/c-1/ai-profile/fill'));
});

it('maps an AI surface the cabinet has not consented to to an AiException', function (): void {
    Http::fake(['*' => Http::response(['code' => 'ai.disabled', 'detail' => 'no consent'], 409)]);

    expect(fn () => Linqelio::contacts()->fillAiProfile('c-1'))->toThrow(AiException::class);
});
