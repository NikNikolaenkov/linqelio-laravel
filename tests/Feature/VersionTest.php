<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Client\HttpClient;

/**
 * The version constant goes into the User-Agent of every request, which is how
 * the platform's telemetry attributes traffic to a release.
 *
 * It read `0.1.0` for the whole of 0.2.0 — nothing checked it, and nothing about
 * releasing forced anyone to look. These tests are the forcing function: a
 * release that updates the changelog and forgets the constant fails here rather
 * than shipping a client that misreports itself for months.
 */

/** The newest released version in the changelog, ignoring the Unreleased heading. */
function newestReleasedVersion(): ?string
{
    $changelog = file_get_contents(__DIR__.'/../../CHANGELOG.md');
    if ($changelog === false) {
        return null;
    }
    // "## [0.3.0] - 2026-08-11" — the first such heading is the newest, because
    // Keep a Changelog orders releases newest first.
    if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $m) !== 1) {
        return null;
    }

    return $m[1];
}

it('reports the version it was released as', function (): void {
    $released = newestReleasedVersion();

    expect($released)->not->toBeNull('CHANGELOG.md has no released version heading');
    expect(HttpClient::VERSION)->toBe($released, sprintf(
        "HttpClient::VERSION is %s but the newest release in CHANGELOG.md is %s.\n".
        'The constant travels in the User-Agent, so a stale one makes the platform '.
        'attribute this release\'s traffic to the previous one.',
        HttpClient::VERSION,
        $released ?? '(none)',
    ));
});

it('puts that version in the User-Agent', function (): void {
    Http::fake(['*' => Http::response(['items' => []], 200)]);

    Linqelio\Laravel\Facades\Linqelio::contacts()->list();

    Http::assertSent(function ($request): bool {
        $ua = $request->header('User-Agent')[0] ?? '';

        return str_contains($ua, 'linqelio-laravel/'.HttpClient::VERSION);
    });
});
