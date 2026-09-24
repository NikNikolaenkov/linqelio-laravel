<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Resources;

use Linqelio\Laravel\Client\BinaryResponse;
use Linqelio\Laravel\Client\HttpClient;
use Linqelio\Laravel\Data\Imports\ContactImport;
use Linqelio\Laravel\Data\Imports\ContactImportChange;
use Linqelio\Laravel\Data\Imports\ImportMapping;
use Linqelio\Laravel\Data\Imports\ImportPreview;
use Linqelio\Laravel\Data\Read;

/**
 * Bulk-loading contacts from a CSV file, in the background.
 *
 *     $job = Linqelio::contactImports()->upload($csv, 'customers.csv');
 *     $mapping = new ImportMapping(
 *         ImportColumn::identity($job->columnOf('phone'), ChannelKind::WaWeb),
 *         ImportColumn::hostRef($job->columnOf('id'), 'crm'),
 *         ImportColumn::consent($job->columnOf('opt_in'), $channelId),
 *     );
 *     $preview = Linqelio::contactImports()->preview($job->id, $mapping);
 *     Linqelio::contactImports()->start($job->id, $mapping);
 *     // poll find($job->id) until finished; report($job->id) for the per-row outcome
 *
 * For a handful of contacts `contacts()->create()` is simpler; this is for the
 * thousands, where a per-row report beats a loop of calls.
 */
final readonly class ContactImportsResource
{
    public function __construct(private HttpClient $client) {}

    /** Upload the file. Nothing is imported until {@see self::start()}. */
    public function upload(string $csv, ?string $filename = null): ContactImport
    {
        $response = $this->client->postRaw('/contact-imports', $csv, Read::compact([
            'filename' => $filename,
        ]), 'text/csv');

        return ContactImport::fromArray($response->data);
    }

    public function uploadPath(string $path): ContactImport
    {
        $bytes = file_get_contents($path);

        return $this->upload($bytes === false ? '' : $bytes, basename($path));
    }

    /**
     * Try a mapping on the first rows without writing anything.
     *
     * @param  int|null  $rows  how many rows to check
     */
    public function preview(string $jobId, ImportMapping $mapping, ?int $rows = null): ImportPreview
    {
        $response = $this->client->post(
            "/contact-imports/{$jobId}/preview",
            $mapping->toArray(),
            Read::compact(['rows' => $rows]),
        );

        return ImportPreview::fromArray($response->data);
    }

    /** Run the import in the background with this mapping. */
    public function start(string $jobId, ImportMapping $mapping): ContactImport
    {
        return ContactImport::fromArray(
            $this->client->post("/contact-imports/{$jobId}/start", $mapping->toArray())->data,
        );
    }

    /** Stop an import that has not finished. Rows already written stay. */
    public function cancel(string $jobId): ContactImportChange
    {
        return ContactImportChange::fromArray($this->client->post("/contact-imports/{$jobId}/cancel")->data);
    }

    public function find(string $jobId): ContactImport
    {
        return ContactImport::fromArray($this->client->get("/contact-imports/{$jobId}")->data);
    }

    /**
     * The cabinet's recent imports.
     *
     * @return array<int, ContactImport>
     */
    public function list(): array
    {
        return array_map(ContactImport::fromArray(...), $this->client->get('/contact-imports')->items());
    }

    /** The per-row outcome of an import, as CSV. */
    public function report(string $jobId): BinaryResponse
    {
        return $this->client->getRaw("/contact-imports/{$jobId}/report");
    }
}
