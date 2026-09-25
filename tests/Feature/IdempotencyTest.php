<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Campaigns\CampaignContent;
use Linqelio\Laravel\Data\Campaigns\CampaignInput;
use Linqelio\Laravel\Data\Enums\ErrorCode;
use Linqelio\Laravel\Exceptions\IdempotencyException;
use Linqelio\Laravel\Exceptions\LinqelioException;
use Linqelio\Laravel\Exceptions\PolicyException;
use Linqelio\Laravel\Facades\Linqelio;
use Linqelio\Laravel\Resources\CampaignsResource;
use Linqelio\Laravel\Resources\ContactImportsResource;

/**
 * The generic Idempotency-Key layer (ADR-0103, issue #140).
 */
function retryingClient(): HttpClient
{
    // The suite runs with one try; these tests need the transport to retry.
    return new HttpClient(
        app(HttpFactory::class),
        'https://linqelio.test/v1',
        'cab-1.k-test.secret',
        retry: ['times' => 3, 'sleep' => 0],
    );
}

/**
 * @return array<int, string>
 */
function sentKeys(): array
{
    return Http::recorded()
        ->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0] ?? '')
        ->values()
        ->all();
}

it('reuses the caller\'s key across the transport\'s own retries', function (): void {
    Http::fakeSequence()
        ->push(['code' => 'provider.unavailable'], 503)
        ->push(['id' => 'cmp-1', 'status' => 'draft'], 201);

    $campaign = (new CampaignsResource(retryingClient()))
        ->create(new CampaignInput('Sale', ['ch-1'], CampaignContent::text('hi')), idempotencyKey: 'sale-2026');

    expect($campaign->id)->toBe('cmp-1')
        ->and(sentKeys())->toBe(['sale-2026', 'sale-2026']);
});

it('generates ONE key per call and repeats it on every retry', function (): void {
    Http::fakeSequence()
        ->push([], 502)
        ->push([], 503)
        ->push(['id' => 'cmp-1'], 200);

    (new CampaignsResource(retryingClient()))->launch('cmp-1');

    $keys = sentKeys();

    expect($keys)->toHaveCount(3)
        ->and($keys[0])->not->toBe('')
        ->and(array_unique($keys))->toHaveCount(1);
});

it('gives each logical call its own key', function (): void {
    Http::fake(['*' => Http::response(['id' => 'cmp-1'], 200)]);

    Linqelio::campaigns()->launch('cmp-1');
    Linqelio::campaigns()->launch('cmp-2');

    $keys = sentKeys();

    expect($keys[0])->not->toBe($keys[1]);
});

it('keeps the upload key across retries of a raw upload', function (): void {
    Http::fakeSequence()
        ->push([], 503)
        ->push(['id' => 'job-1', 'status' => 'uploaded'], 201);

    (new ContactImportsResource(retryingClient()))->upload("phone\n380500000000\n", 'a.csv', 'import-a');

    expect(sentKeys())->toBe(['import-a', 'import-a']);
});

it('passes the caller\'s key on the wrapped replayable operations', function (): void {
    Http::fake(['*' => Http::response(['id' => 'x'], 200)]);

    Linqelio::groups()->leave('cv-1', idempotencyKey: 'leave-1');
    Linqelio::contacts()->fillAiProfile('c-1', idempotencyKey: 'fill-1');

    expect(sentKeys())->toBe(['leave-1', 'fill-1']);
});

it('says when the answer was replayed from the idempotency store', function (): void {
    Http::fakeSequence()
        ->push(['id' => 'cmp-1'], 201, ['Idempotent-Replayed' => 'true'])
        ->push(['id' => 'cmp-1'], 201);

    $client = retryingClient();

    expect($client->post('/campaigns', ['name' => 'a'], idempotencyKey: 'k')->replayed())->toBeTrue()
        ->and($client->post('/campaigns', ['name' => 'a'], idempotencyKey: 'k')->replayed())->toBeFalse();
});

it('maps a request still running under the key to IdempotencyException, with Retry-After', function (): void {
    Http::fake(['*' => Http::response(
        ['code' => 'idempotency.in_progress', 'detail' => 'a request with this Idempotency-Key is still running'],
        409,
        ['Retry-After' => '2'],
    )]);

    try {
        Linqelio::campaigns()->create(new CampaignInput('Sale', ['ch-1'], CampaignContent::text('hi')), 'sale-2026');
        $this->fail('expected an IdempotencyException');
    } catch (IdempotencyException $e) {
        expect($e->errorCode())->toBe(ErrorCode::IdempotencyInProgress)
            ->and($e->isInProgress())->toBeTrue()
            ->and($e->isKeyReused())->toBeFalse()
            ->and($e->isRetryable())->toBeTrue()
            ->and($e->retryAfter())->toBe(2)
            ->and($e->status())->toBe(409);
    }
});

it('maps a key reused for another request to IdempotencyException — not retryable', function (): void {
    Http::fake(['*' => Http::response(['code' => 'idempotency.key_reused'], 409)]);

    try {
        Linqelio::groups()->create('ch-1', 'Team', ['c-1'], 'team-1');
        $this->fail('expected an IdempotencyException');
    } catch (IdempotencyException $e) {
        expect($e->isKeyReused())->toBeTrue()
            ->and($e->isRetryable())->toBeFalse()
            ->and($e->retryAfter())->toBeNull();
    }
});

it('still lets a plain LinqelioException catch block handle it', function (): void {
    Http::fake(['*' => Http::response(['code' => 'idempotency.in_progress'], 409, ['Retry-After' => '1'])]);

    expect(fn () => Linqelio::webhooks()->register('https://app.test/hook', idempotencyKey: 'wh-1'))
        ->toThrow(LinqelioException::class);
});

it('reads Retry-After as an HTTP date too, and prefers the problem\'s own figure on a policy refusal', function (): void {
    Http::fake([
        '*/one' => Http::response(['code' => 'idempotency.in_progress'], 409, [
            'Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 30),
        ]),
        '*/two' => Http::response(['code' => 'policy.rate_limited', 'retryAfter' => 7], 429, ['Retry-After' => '9']),
        '*/three' => Http::response(['code' => 'policy.rate_limited'], 429, ['Retry-After' => '9']),
    ]);

    $caught = [];
    foreach (['/one', '/two', '/three'] as $path) {
        try {
            retryingClientOnce()->post($path);
        } catch (LinqelioException $e) {
            $caught[] = $e;
        }
    }

    expect($caught[0])->toBeInstanceOf(IdempotencyException::class)
        ->and($caught[0]->retryAfter())->toBeGreaterThanOrEqual(28)->toBeLessThanOrEqual(30)
        ->and($caught[1])->toBeInstanceOf(PolicyException::class)
        ->and($caught[1]->retryAfter())->toBe(7)
        ->and($caught[2]->retryAfter())->toBe(9);

    Http::assertSent(fn (Request $r): bool => $r->hasHeader('Idempotency-Key'));
});

function retryingClientOnce(): HttpClient
{
    return new HttpClient(app(HttpFactory::class), 'https://linqelio.test/v1', 'cab-1.k-test.secret', retry: ['times' => 1, 'sleep' => 0]);
}
