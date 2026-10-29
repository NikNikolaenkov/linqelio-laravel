<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Linqelio\Laravel\Facades\Linqelio;

it('reads an uploaded file back as its bytes and content type', function (): void {
    Http::fake(['*' => Http::response('%PDF-bytes', 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="invoice.pdf"',
    ])]);

    $file = Linqelio::media()->download('md-1');

    expect($file->bytes)->toBe('%PDF-bytes')
        ->and($file->contentType)->toBe('application/pdf')
        ->and($file->filename)->toBe('invoice.pdf');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
        && str_ends_with($r->url(), '/media/md-1'));
});
