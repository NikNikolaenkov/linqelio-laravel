<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Data\Enums\ChannelKind;
use Linqelio\Laravel\Data\Enums\ContactImportStatus;
use Linqelio\Laravel\Data\Enums\ContactStatus;
use Linqelio\Laravel\Data\Enums\ExportStatus;
use Linqelio\Laravel\Data\Imports\ContactExportFilter;
use Linqelio\Laravel\Data\Imports\ImportColumn;
use Linqelio\Laravel\Data\Imports\ImportMapping;
use Linqelio\Laravel\Exceptions\ContactException;
use Linqelio\Laravel\Facades\Linqelio;

function importBody(string $status = 'uploaded'): array
{
    return [
        'id' => 'job-1', 'status' => $status, 'filename' => 'customers.csv', 'sizeBytes' => 42,
        'headers' => ['phone', 'id', 'opt_in'], 'totalRows' => 2, 'sampleRows' => [['380500000000', '17', 'yes']],
        'processedRows' => 0, 'created' => 0, 'updated' => 0, 'rejected' => 0, 'warnings' => 0,
        'mergeProposals' => 0, 'consentsRecorded' => 0, 'createdBy' => ['type' => 'api_key', 'id' => 'k-1'],
    ];
}

it('uploads the CSV raw, with the filename in the query', function (): void {
    Http::fake(['*' => Http::response(importBody(), 201)]);

    $job = Linqelio::contactImports()->upload("phone,id,opt_in\n380500000000,17,yes\n", 'customers.csv');

    expect($job->status)->toBe(ContactImportStatus::Uploaded)
        ->and($job->columnOf('opt_in'))->toBe(2)
        ->and($job->columnOf('nope'))->toBeNull()
        ->and($job->sampleRows)->toBe([['380500000000', '17', 'yes']]);

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST'
        && str_contains($r->url(), '/contact-imports?filename=customers.csv')
        && $r->header('Content-Type')[0] === 'text/csv'
        && str_starts_with($r->body(), 'phone,id,opt_in'));
});

it('previews and starts with the same mapping', function (): void {
    Http::fake([
        '*/preview*' => Http::response(['rowsChecked' => 2, 'totalRows' => 2, 'valid' => 1, 'wouldCreate' => 1, 'wouldUpdate' => 0, 'rejected' => 1, 'mergeProposals' => 0, 'withConsent' => 1, 'issues' => [['row' => 2, 'severity' => 'error', 'code' => 'bad_phone']]]),
        '*/start' => Http::response(importBody('queued'), 202),
    ]);

    $mapping = new ImportMapping(
        ImportColumn::identity(0, ChannelKind::WaWeb),
        ImportColumn::hostRef(1, 'crm'),
        ImportColumn::consent(2, 'ch-1'),
    );

    $preview = Linqelio::contactImports()->preview('job-1', $mapping, 50);
    $job = Linqelio::contactImports()->start('job-1', $mapping);

    expect($preview->hasErrors())->toBeTrue()
        ->and($preview->issues[0]['code'])->toBe('bad_phone')
        ->and($job->status)->toBe(ContactImportStatus::Queued);

    $expected = ['columns' => [
        ['column' => 0, 'target' => 'identity', 'channelKind' => 'wa_web'],
        ['column' => 1, 'target' => 'hostRef', 'system' => 'crm'],
        ['column' => 2, 'target' => 'consent', 'channelId' => 'ch-1'],
    ]];

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/contact-imports/job-1/preview?rows=50') && $r->data() === $expected);
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/contact-imports/job-1/start') && $r->data() === $expected);
});

it('cancels, finds, lists and fetches the report', function (): void {
    Http::fake([
        '*/cancel' => Http::response(['job' => importBody('cancelled'), 'changed' => true]),
        '*/report' => Http::response("row,outcome\n1,created\n", 200, ['Content-Type' => 'text/csv']),
        '*/contact-imports/job-1' => Http::response(importBody('completed')),
        '*/contact-imports' => Http::response(['items' => [importBody()]]),
    ]);

    $cancel = Linqelio::contactImports()->cancel('job-1');
    $report = Linqelio::contactImports()->report('job-1');

    expect($cancel->changed)->toBeTrue()
        ->and($cancel->import->status->isFinished())->toBeTrue()
        ->and(Linqelio::contactImports()->find('job-1')->status)->toBe(ContactImportStatus::Completed)
        ->and(Linqelio::contactImports()->list())->toHaveCount(1)
        ->and($report->contentType)->toBe('text/csv')
        ->and($report->bytes)->toBe("row,outcome\n1,created\n");
});

it('exports with a filter and downloads the file', function (): void {
    Http::fake([
        '*/file' => Http::response("id,name\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="contacts.csv"']),
        '*/contact-exports' => Http::response(['id' => 'exp-1', 'status' => 'queued', 'filter' => ['tags' => ['vip'], 'status' => 'active'], 'scoped' => true], 202),
    ]);

    $export = Linqelio::contactExports()->create(new ContactExportFilter(tags: ['vip'], status: ContactStatus::Active));
    $file = Linqelio::contactExports()->download('exp-1');

    expect($export->status)->toBe(ExportStatus::Queued)
        ->and($export->status->isReady())->toBeFalse()
        ->and($export->filter->status)->toBe(ContactStatus::Active)
        ->and($export->scoped)->toBeTrue()
        ->and($file->filename)->toBe('contacts.csv');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r->data() === ['tags' => ['vip'], 'status' => 'active']);
});

it('maps job refusals to the contact family', function (): void {
    Http::fake(['*' => Http::response(['code' => 'contact.export_expired', 'detail' => 'gone'], 410)]);

    expect(fn () => Linqelio::contactExports()->download('exp-1'))->toThrow(ContactException::class);
});
