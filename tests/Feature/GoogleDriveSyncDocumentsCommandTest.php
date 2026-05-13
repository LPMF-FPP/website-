<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\TestRequest;
use App\Models\User;
use App\Models\UserGoogleDriveToken;
use App\Services\GoogleDriveOAuthService;
use App\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GoogleDriveSyncDocumentsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        settings_fake_clear();

        parent::tearDown();
    }

    public function test_dry_run_lists_only_retryable_uploaded_documents_for_request(): void
    {
        $testRequest = TestRequest::factory()->create();

        $skipped = Document::factory()->create([
            'test_request_id' => $testRequest->id,
            'source' => 'upload',
            'document_type' => 'request_letter',
            'extra' => ['google_drive' => ['status' => 'skipped']],
        ]);
        $failed = Document::factory()->create([
            'test_request_id' => $testRequest->id,
            'source' => 'upload',
            'document_type' => 'expert_witness_request',
            'extra' => ['google_drive' => ['status' => 'failed']],
        ]);
        $uploaded = Document::factory()->create([
            'test_request_id' => $testRequest->id,
            'source' => 'upload',
            'document_type' => 'evidence_photo',
            'extra' => ['google_drive' => ['status' => 'uploaded']],
        ]);
        $generated = Document::factory()->generated()->create([
            'test_request_id' => $testRequest->id,
            'document_type' => 'ba_penerimaan',
            'extra' => ['google_drive' => ['status' => 'skipped']],
        ]);

        $this->artisan('lims:google-drive-sync-documents', [
            '--request' => $testRequest->id,
            '--dry-run' => true,
        ])
            ->expectsTable(
                ['ID', 'Permintaan', 'Tipe', 'File', 'Status Drive'],
                [
                    [$skipped->id, $testRequest->id, 'request_letter', $skipped->filename, 'skipped'],
                    [$failed->id, $testRequest->id, 'expert_witness_request', $failed->filename, 'failed'],
                ],
            )
            ->expectsOutput('Dry run selesai; tidak ada upload yang dilakukan.')
            ->assertExitCode(0);

        $this->assertSame('uploaded', data_get($uploaded->fresh()->extra, 'google_drive.status'));
        $this->assertSame('skipped', data_get($generated->fresh()->extra, 'google_drive.status'));
    }

    public function test_sync_falls_back_to_current_user_when_configured_uploader_token_is_revoked(): void
    {
        Storage::fake('public');
        $configuredUploader = User::factory()->create();
        $currentUser = User::factory()->create();
        UserGoogleDriveToken::create([
            'user_id' => $configuredUploader->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'expired-refresh-token',
            'expires_at' => now()->subMinute(),
        ]);
        UserGoogleDriveToken::create([
            'user_id' => $currentUser->id,
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
            'expires_at' => now()->addHour(),
        ]);
        settings_fake(['google_drive.uploader_user_id' => $configuredUploader->id, 'google_drive.folder_id' => 'root-folder'], true);

        $document = Document::factory()->create([
            'source' => 'upload',
            'document_type' => 'test_results',
            'file_path' => 'testing/result.pdf',
            'path' => 'testing/result.pdf',
            'file_size' => 12,
            'mime_type' => 'application/pdf',
            'extra' => ['google_drive' => ['status' => 'skipped']],
        ]);
        Storage::disk('public')->put('testing/result.pdf', 'pdf contents');

        $oauth = \Mockery::mock(GoogleDriveOAuthService::class);
        $oauth->shouldReceive('accessTokenFor')
            ->once()
            ->with(\Mockery::on(fn (User $user): bool => $user->is($configuredUploader)))
            ->andThrow(new RuntimeException('Google Drive token refresh failed: Token has been expired or revoked.'));
        $oauth->shouldReceive('accessTokenFor')
            ->once()
            ->with(\Mockery::on(fn (User $user): bool => $user->is($currentUser)))
            ->andReturn('fallback-access-token');
        app()->instance(GoogleDriveOAuthService::class, $oauth);

        $drive = \Mockery::mock(GoogleDriveService::class);
        $drive->shouldReceive('findFoldersWithAccessToken')->andReturn([['id' => 'existing-folder']]);
        $drive->shouldReceive('uploadWithAccessToken')
            ->once()
            ->with('fallback-access-token', \Mockery::type('string'), 'pdf contents', 'application/pdf', 'existing-folder')
            ->andReturn(['id' => 'drive-file-id', 'name' => 'uploaded.pdf', 'webViewLink' => 'https://drive.test/file']);
        app()->instance(GoogleDriveService::class, $drive);

        $this->artisan('lims:google-drive-sync-documents', [
            '--document' => [$document->id],
            '--user' => $currentUser->id,
        ])
            ->expectsOutput('Sinkronisasi selesai. Uploaded: 1, skipped: 0, failed: 0.')
            ->assertExitCode(0);

        $document->refresh();
        $this->assertSame('uploaded', data_get($document->extra, 'google_drive.status'));
        $this->assertSame($currentUser->id, data_get($document->extra, 'google_drive.uploaded_by_user_id'));
    }

    public function test_google_drive_health_reports_revoked_configured_uploader_token(): void
    {
        $configuredUploader = User::factory()->create();
        UserGoogleDriveToken::create([
            'user_id' => $configuredUploader->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'expired-refresh-token',
            'expires_at' => now()->subMinute(),
        ]);
        settings_fake(['google_drive.uploader_user_id' => $configuredUploader->id], true);

        Document::factory()->create([
            'source' => 'upload',
            'extra' => ['google_drive' => ['status' => 'skipped']],
        ]);
        Document::factory()->create([
            'source' => 'upload',
            'extra' => [],
        ]);

        $oauth = \Mockery::mock(GoogleDriveOAuthService::class);
        $oauth->shouldReceive('accessTokenFor')
            ->once()
            ->with(\Mockery::on(fn (User $user): bool => $user->is($configuredUploader)))
            ->andThrow(new RuntimeException('Google Drive token refresh failed: Token has been expired or revoked.'));
        app()->instance(GoogleDriveOAuthService::class, $oauth);

        $this->artisan('lims:google-drive-health')
            ->expectsOutput('Token Google Drive akun uploader sudah tidak valid atau dicabut oleh Google. Hubungkan ulang akun Google Drive uploader di Profil, lalu jalankan sinkronisasi ulang dokumen tertunda.')
            ->expectsOutput('Google Drive belum sehat. Hubungkan ulang akun uploader sebelum mengandalkan sinkronisasi otomatis.')
            ->assertExitCode(1);
    }
}
