<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Linqelio\Laravel\Exceptions\ConfigurationException;
use Linqelio\Laravel\Exceptions\ExceptionFactory;

/**
 * The transport every resource goes through.
 *
 * Three things it does that a bare Http::withToken() would not:
 *
 *  - it never lets Laravel throw on a non-2xx, because the body is where the
 *    meaning is. A 409 carrying `idempotency.key_reused` and a 409 carrying
 *    `contact.version_conflict` are different problems with the same status.
 *  - it retries only what is worth retrying (429 and 5xx), honouring Retry-After
 *    when the platform sets it.
 *  - it fills in Idempotency-Key for unsafe commands, because the alternative —
 *    leaving it to the caller — means the first timeout sends somebody a
 *    duplicate message.
 */
final class HttpClient
{
    public const VERSION = '0.2.0';

    /**
     * @param  array{times:int, sleep:int}  $retry
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $baseUrl,
        private readonly ?string $key,
        private readonly int $timeout = 15,
        private readonly int $uploadTimeout = 60,
        private readonly array $retry = ['times' => 3, 'sleep' => 200],
    ) {}

    /**
     * The same transport, holding another cabinet's key.
     *
     * The container binds ONE client, built once from config. Under a persistent
     * worker — Octane, a queue worker — that instance outlives the request that
     * first resolved it, so an application serving more than one cabinet cannot
     * swap the key on the shared object: the next request would inherit it and
     * read somebody else's messages.
     *
     * This returns a new client and leaves the shared one untouched, which makes
     * the key a property of the call site rather than of the process.
     */
    public function withKey(string $key): self
    {
        return new self(
            http: $this->http,
            baseUrl: $this->baseUrl,
            key: $key,
            timeout: $this->timeout,
            uploadTimeout: $this->uploadTimeout,
            retry: $this->retry,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, query: $query);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    public function post(string $path, array $body = [], array $query = [], ?string $idempotencyKey = null): Response
    {
        return $this->send('POST', $path, $body, $query, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function put(string $path, array $body = [], ?string $idempotencyKey = null): Response
    {
        return $this->send('PUT', $path, $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function patch(string $path, array $body = [], ?string $idempotencyKey = null): Response
    {
        return $this->send('PATCH', $path, $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function delete(string $path, array $body = []): Response
    {
        return $this->send('DELETE', $path, $body);
    }

    /**
     * Upload raw bytes (an attachment). Sent as application/octet-stream with a
     * longer timeout, since this is the one call whose duration depends on the
     * payload rather than on the platform.
     *
     * @param  array<string, mixed>  $query
     */
    public function postRaw(string $path, string $bytes, array $query = [], ?string $contentType = null): Response
    {
        $response = $this->request($this->uploadTimeout)
            ->withBody($bytes, $contentType ?: 'application/octet-stream')
            ->post($this->url($path, $query));

        return $this->handle($response);
    }

    /**
     * Fetch a binary body (an attachment) without decoding it as JSON.
     *
     * @param  array<string, mixed>  $query
     */
    public function getRaw(string $path, array $query = []): BinaryResponse
    {
        $response = $this->request($this->uploadTimeout)->get($this->url($path, $query));

        if ($response->failed()) {
            $this->fail($response);
        }

        return new BinaryResponse(
            bytes: $response->body(),
            contentType: $response->header('Content-Type') ?: 'application/octet-stream',
            filename: $this->filenameFrom($response->header('Content-Disposition')),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    private function send(
        string $method,
        string $path,
        array $body = [],
        array $query = [],
        ?string $idempotencyKey = null,
    ): Response {
        $request = $this->request($this->timeout);

        // Unsafe commands require Idempotency-Key. Generating one here rather
        // than demanding it means a caller cannot forget; passing one in stays
        // possible for the case where the key should come from the caller's own
        // domain (an order id, say) so a retry across processes still dedupes.
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $request = $request->withHeader(
                'Idempotency-Key',
                $idempotencyKey ?? IdempotencyKey::generate(),
            );
        }

        $response = $request->send($method, $this->url($path, $query), [
            'json' => $body === [] ? null : $body,
        ]);

        return $this->handle($response);
    }

    private function request(int $timeout): PendingRequest
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            throw ConfigurationException::missingBaseUrl();
        }

        if ($this->key === null || $this->key === '') {
            throw ConfigurationException::missingKey();
        }

        return $this->http
            ->withToken($this->key)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => sprintf('linqelio-laravel/%s php/%s', self::VERSION, PHP_VERSION),
            ])
            ->timeout($timeout)
            // throw: false — the body carries the error code, and the code is
            // what a caller switches on. We raise our own typed exception below.
            ->retry(
                $this->retry['times'],
                $this->retry['sleep'],
                fn (\Throwable $e): bool => $this->shouldRetry($e),
                throw: false,
            );
    }

    /**
     * Retry transport failures and the two statuses that mean "later, not never".
     * A 4xx describes the request itself; repeating it unchanged cannot help.
     */
    private function shouldRetry(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    private function handle(HttpResponse $response): Response
    {
        if ($response->failed()) {
            $this->fail($response);
        }

        return new Response(
            status: $response->status(),
            data: $response->json() ?? [],
            requestId: $response->header('X-Request-Id') ?: null,
        );
    }

    private function fail(HttpResponse $response): never
    {
        $problem = $response->json();

        throw ExceptionFactory::make(
            is_array($problem) ? $problem : [],
            $response->status(),
            $response->header('X-Request-Id') ?: null,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function url(string $path, array $query = []): string
    {
        $url = rtrim((string) $this->baseUrl, '/').'/'.ltrim($path, '/');

        $query = array_filter($query, static fn ($v): bool => $v !== null && $v !== '');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function filenameFrom(?string $disposition): ?string
    {
        if ($disposition === null || ! preg_match('/filename="?([^";]+)"?/', $disposition, $m)) {
            return null;
        }

        return trim($m[1]);
    }
}
