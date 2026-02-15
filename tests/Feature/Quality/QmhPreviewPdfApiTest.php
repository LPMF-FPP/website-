<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhPreviewPdfApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();

        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('setWarnings')->andReturnSelf();
        $mockPdf->shouldReceive('setOption')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('%PDF-1.4 preview');

        Pdf::shouldReceive('loadHTML')
            ->withArgs(function (string $html): bool {
                $this->assertStringContainsString('No. Dokumen', $html);

                return true;
            })
            ->andReturn($mockPdf);
    }

    public function test_preview_pdf_endpoint_returns_pdf_binary(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->postJson('/api/quality/preview/pdf', [
                'doc_type' => 'sop',
                'clause' => 4,
                'doc_code' => 'QMH-PREVIEW-001',
                'title' => 'Preview SOP',
                'answers_json' => [
                    'purpose' => '<p><strong>Tujuan</strong></p>',
                ],
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    private function createQmhPermissions(): void
    {
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
            'permission_id' => $createPermission->id,
        ]);
    }
}
