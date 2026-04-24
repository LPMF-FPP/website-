<?php

declare(strict_types=1);

use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemSettingSeeder::class);
    settings_fake(['notifications.whatsapp.enabled' => false]);
    settings_forget_cache();
    Queue::fake();
    $this->user = User::factory()->create(['role' => 'admin']);
});

function createHandoverDocument(TestRequest $request): void
{
    Document::factory()->create([
        'investigator_id' => $request->investigator_id,
        'test_request_id' => $request->id,
        'document_type' => 'ba_penyerahan',
        'source' => 'generated',
        'filename' => 'ba-penyerahan.pdf',
        'original_filename' => 'BA-ST/001/IV/2026/FARMAPOL-ba-penyerahan.pdf',
        'file_path' => 'documents/ba-penyerahan.pdf',
        'path' => 'documents/ba-penyerahan.pdf',
        'storage_disk' => 'public',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
    ]);
}

it('marks remaining label step as complete when no sample has leftover quantity', function (): void {
    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
    ]);

    Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'sample_code' => 'SAMP-NO-SISA',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    createHandoverDocument($request);

    $response = $this->actingAs($this->user)
        ->get(route('delivery.show', $request));

    $response->assertOk();
    $response->assertSee('Tidak ada sisa sampel yang perlu dilabeli.');
    $response->assertSee('langkah ini ditandai selesai otomatis', false);
    $response->assertSee('Kirim Notifikasi');
});

it('allows delivery completion without remaining labels when survey is complete and no leftover exists', function (): void {
    $request = TestRequest::factory()->create([
        'status' => 'ready_for_delivery',
    ]);

    Sample::factory()->create([
        'test_request_id' => $request->id,
        'status' => 'ready_for_delivery',
        'sample_code' => 'SAMP-HABIS-UJI',
        'package_quantity' => 0,
        'quantity' => 0,
    ]);

    createHandoverDocument($request);

    CustomerSurvey::create([
        'test_request_id' => $request->id,
        'respondent_name' => 'Pengguna Uji',
        'respondent_institution' => 'Penyidik',
        'respondent_job_category' => 'Polri',
        'request_type' => 'Kimia - Fisika',
        'voluntary_statement' => true,
        'answers' => [
            'persyaratan' => 4,
            'prosedur' => 4,
            'ketepatan_waktu' => 4,
            'kesesuaian_hasil' => 4,
            'kompetensi' => 4,
            'sikap' => 4,
            'pengaduan' => 4,
            'fasilitas' => 4,
        ],
        'suggestion' => 'Sudah baik.',
        'score_avg' => 4,
        'submitted_at' => now(),
        'submitted_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('delivery.complete', $request))
        ->assertRedirect()
        ->assertSessionHas('success', 'Penyerahan berhasil diselesaikan. Status permintaan telah diperbarui.');

    expect($request->fresh())
        ->status->toBe('completed');
});
