<?php

namespace Tests\Feature\Settings;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyBackupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_marks_success_backup_as_failed_when_artifact_missing(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $backup = BackupRun::create([
            'mode' => 'emergency',
            'status' => 'success',
            'triggered_by' => $user->id,
            'artifact_dir' => '/tmp/missing-backup-dir',
            'db_dump_path' => '/tmp/missing-db.sql.gz',
            'storage_archive_path' => '/tmp/missing-storage.tar.gz',
            'manifest_path' => '/tmp/missing-manifest.json',
        ]);

        $response = $this->actingAs($user)->getJson('/api/settings/emergency-backup');

        $response->assertOk()
            ->assertJsonPath('backups.0.id', $backup->id)
            ->assertJsonPath('backups.0.status', 'failed');

        $backup->refresh();
        $this->assertSame('failed', $backup->status);
        $this->assertStringContainsString('Backup artifact missing', (string) $backup->error_message);
    }
}
