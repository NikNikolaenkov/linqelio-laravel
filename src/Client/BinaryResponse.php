<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Client;

use Illuminate\Http\Response as LaravelResponse;

/**
 * Raw bytes fetched from the API — an attachment, streamed through the platform
 * rather than served from the object store directly.
 *
 * The platform proxies attachments on purpose: a presigned storage URL carries
 * its own authorisation and outlives the request that produced it, so serving
 * one would hand out access that no longer answers to the cabinet's rules.
 */
final readonly class BinaryResponse
{
    public function __construct(
        public string $bytes,
        public string $contentType,
        public ?string $filename = null,
    ) {}

    public function size(): int
    {
        return strlen($this->bytes);
    }

    public function save(string $path): bool
    {
        return file_put_contents($path, $this->bytes) !== false;
    }

    /**
     * Hand the bytes to the browser.
     *
     * nosniff is not optional here: the content type comes from whoever sent the
     * attachment, so without it a file claiming to be HTML would execute on your
     * origin.
     */
    public function toResponse(bool $download = false): LaravelResponse
    {
        $disposition = $download ? 'attachment' : 'inline';
        $name = $this->filename !== null ? '; filename="'.str_replace('"', '', $this->filename).'"' : '';

        return new LaravelResponse($this->bytes, 200, [
            'Content-Type' => $this->contentType,
            'Content-Length' => (string) $this->size(),
            'Content-Disposition' => $disposition.$name,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
