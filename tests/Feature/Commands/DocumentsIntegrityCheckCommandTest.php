<?php

declare(strict_types=1);

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('passes when all active documents exist on storage', function () {
    Storage::fake('public');

    $document = Document::factory()->create([
        'storage_disk' => 'public',
        'file_path' => 'investigators/test/present.pdf',
        'path' => 'investigators/test/present.pdf',
    ]);

    Storage::disk('public')->put($document->file_path, 'ok');

    $this->artisan('documents:integrity-check', [
        '--warn-threshold' => 1,
        '--ratio-threshold' => 5,
        '--sample' => 3,
    ])
        ->expectsOutputToContain('File hilang   : 0')
        ->assertExitCode(0);
});

it('fails when missing document files exceed threshold', function () {
    Storage::fake('public');

    Document::factory()->create([
        'storage_disk' => 'public',
        'file_path' => 'investigators/test/missing.pdf',
        'path' => 'investigators/test/missing.pdf',
    ]);

    $this->artisan('documents:integrity-check', [
        '--warn-threshold' => 1,
        '--ratio-threshold' => 5,
        '--sample' => 3,
    ])
        ->expectsOutputToContain('Integrity threshold exceeded.')
        ->assertExitCode(1);
});
