<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMaintenanceOrphanSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Gate::define('manage-settings', fn () => true);
    }

    public function test_cleanup_orphaned_blocks_upload_folders_by_default(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Storage::disk('public')->put(
            'investigators/orphan-upload/BA-RIM/001/I/2026/FPP/uploads/request_letter/sample.pdf',
            'dummy'
        );

        $response = $this->actingAs($user)
            ->postJson('/api/settings/documents/cleanup-orphaned');

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_manual_review', true);

        Storage::disk('public')->assertExists(
            'investigators/orphan-upload/BA-RIM/001/I/2026/FPP/uploads/request_letter/sample.pdf'
        );
    }

    public function test_cleanup_orphaned_deletes_generated_only_and_skips_upload_protected_folders(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Storage::disk('public')->put(
            'investigators/orphan-generated/BA-RIM/001/I/2026/FPP/generated/laporan_hasil_uji/lhu.pdf',
            'generated'
        );
        Storage::disk('public')->put(
            'investigators/orphan-upload/BA-RIM/002/I/2026/FPP/uploads/request_letter/surat.pdf',
            'upload'
        );

        $response = $this->actingAs($user)
            ->postJson('/api/settings/documents/cleanup-orphaned');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('skipped_upload_protected', 1);

        Storage::disk('public')->assertMissing(
            'investigators/orphan-generated/BA-RIM/001/I/2026/FPP/generated/laporan_hasil_uji/lhu.pdf'
        );
        Storage::disk('public')->assertExists(
            'investigators/orphan-upload/BA-RIM/002/I/2026/FPP/uploads/request_letter/surat.pdf'
        );
    }
}
