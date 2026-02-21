<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhPendukungApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
        Storage::fake('local');
    }

    public function test_can_create_pendukung_document_via_api(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->post('/api/quality/pendukung', [
                'doc_code' => 'DP-5.001',
                'title' => 'Sertifikat Kalibrasi',
                'clause' => 5,
                'file' => UploadedFile::fake()->createWithContent('sertifikat.pdf', "%PDF-1.4\nFile Pendukung"),
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.doc_code', 'DP-5.001');

        $this->assertDatabaseHas('qmh_documents', [
            'doc_code' => 'DP-5.001',
            'doc_type' => 'pendukung',
            'clause' => 5,
        ]);
    }

    public function test_by_clause_endpoint_returns_only_matching_clause(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->post('/api/quality/pendukung', [
                'doc_code' => 'DP-4.002',
                'title' => 'Diagram Klausul 4',
                'clause' => 4,
                'file' => UploadedFile::fake()->createWithContent('diagram-4.pdf', "%PDF-1.4\nKlausul4"),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $this->actingAs($user)
            ->post('/api/quality/pendukung', [
                'doc_code' => 'DP-6.001',
                'title' => 'Diagram Klausul 6',
                'clause' => 6,
                'file' => UploadedFile::fake()->createWithContent('diagram-6.pdf', "%PDF-1.4\nKlausul6"),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $response = $this->actingAs($user)
            ->getJson('/api/quality/pendukung/clause/4');

        $response->assertOk();
        $rows = collect($response->json('data'));

        $this->assertCount(1, $rows);
        $this->assertSame('DP-4.002', $rows->first()['doc_code']);
    }

    private function createQmhPermissions(): void
    {
        $viewPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.view'],
            [
                'display_name' => 'Lihat Quality Management Hub',
                'module' => 'qmh',
                'action' => 'view',
            ]
        );

        $createPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.create'],
            [
                'display_name' => 'Buat Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'create',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $viewPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);
    }
}
