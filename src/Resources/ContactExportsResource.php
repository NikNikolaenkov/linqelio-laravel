<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\BinaryResponse;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Imports\ContactExport;
use Linqelio\Laravel\Data\Imports\ContactExportFilter;

/**
 * Contacts out to a CSV file, built in the background. Needs contacts:export.
 *
 * The file is kept for a while and then swept: download it once it is ready
 * rather than keeping the job id around as a link.
 */
final readonly class ContactExportsResource
{
    public function __construct(private HttpClient $client) {}

    public function create(?ContactExportFilter $filter = null): ContactExport
    {
        $body = $filter?->toArray() ?? [];

        return ContactExport::fromArray($this->client->post('/contact-exports', $body)->data);
    }

    /**
     * The key's recent exports.
     *
     * @return array<int, ContactExport>
     */
    public function list(): array
    {
        return array_map(ContactExport::fromArray(...), $this->client->get('/contact-exports')->items());
    }

    public function find(string $jobId): ContactExport
    {
        return ContactExport::fromArray($this->client->get("/contact-exports/{$jobId}")->data);
    }

    /**
     * The CSV. Before it is ready the answer is 409, after it expired 410 —
     * check {@see ContactExport::$status} first.
     */
    public function download(string $jobId): BinaryResponse
    {
        return $this->client->getRaw("/contact-exports/{$jobId}/file");
    }
}
