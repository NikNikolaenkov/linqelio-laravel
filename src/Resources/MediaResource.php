<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Illuminate\Http\UploadedFile;
use Linqelio\Laravel\Client\BinaryResponse;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\MediaContent;

/**
 * Attachments, which move in opposite directions.
 *
 * Outbound is pull-based: the file goes to the platform's object store and the
 * provider fetches it by URL, so an upload has to happen before the send.
 *
 * Inbound is proxied: the platform re-reads the object with its own credentials
 * and streams it back under your key. The URL stored on an old message is a
 * presigned link that has long expired — {@see self::fetch()} is the way to read
 * one later.
 */
final readonly class MediaResource
{
    public function __construct(private HttpClient $client) {}

    /**
     * Upload bytes and get back a reference to put in a send command.
     */
    public function upload(string $bytes, ?string $filename = null, ?string $mime = null): MediaContent
    {
        $response = $this->client->postRaw('/media', $bytes, array_filter([
            'filename' => $filename,
            'mime' => $mime,
        ], static fn ($v): bool => $v !== null), $mime);

        return MediaContent::fromArray($response->data);
    }

    /**
     * Upload a file from a request.
     *
     * The browser-supplied MIME is passed along as a hint; the platform sniffs
     * the bytes when it is absent or generic, so a mislabelled upload still ends
     * up with a sensible type.
     */
    public function uploadFile(UploadedFile $file): MediaContent
    {
        $contents = $file->get();

        return $this->upload(
            bytes: is_string($contents) ? $contents : '',
            filename: $file->getClientOriginalName(),
            mime: $file->getMimeType(),
        );
    }

    public function uploadPath(string $path): MediaContent
    {
        $bytes = file_get_contents($path);

        return $this->upload(
            bytes: $bytes === false ? '' : $bytes,
            filename: basename($path),
            mime: mime_content_type($path) ?: null,
        );
    }

    /**
     * Read a message's attachment, streamed through the platform.
     *
     * Authorised by the message's cabinet: a message you cannot see is a 404,
     * not a redirect to storage.
     */
    public function fetch(string $messageId): BinaryResponse
    {
        return $this->client->getRaw("/messages/{$messageId}/media");
    }
}
