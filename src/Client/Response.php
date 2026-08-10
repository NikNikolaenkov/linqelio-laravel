<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Client;

/**
 * A successful API response, already decoded.
 *
 * Failures never reach here — they are raised as typed exceptions by the client,
 * so a resource method can read this without checking anything first.
 */
final readonly class Response
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $status,
        public array $data,
        public ?string $requestId = null,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * The `items` of a list response, or an empty array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        $items = $this->data['items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    /** The cursor for the next page, when the response is paginated. */
    public function nextCursor(): ?string
    {
        $cursor = data_get($this->data, 'pageInfo.nextCursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }
}
