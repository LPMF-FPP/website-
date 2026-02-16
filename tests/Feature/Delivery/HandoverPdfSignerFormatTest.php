<?php

namespace Tests\Feature\Delivery;

use App\Models\Delivery;
use App\Models\Document;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoverPdfSignerFormatTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('setOption')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('%PDF-1.4 mock handover');

        Pdf::shouldReceive('loadHTML')->andReturn($mockPdf);

        $this->mock(NumberingService::class, function ($mock) {
            $mock->shouldReceive('issue')->andReturn('BA-ST-001-I-2026-FPP');
            $mock->shouldReceive('preview')->andReturnUsing(function (string $scope, array $context = [], ?int $sequence = null): string {
                if ($scope === 'ba_penyerahan') {
                    return 'BA-ST-001-I-2026-FPP';
                }

                if ($scope === 'lhu') {
                    return 'LHU-LPMF-'.str_pad((string) ($sequence ?? 1), 3, '0', STR_PAD_LEFT);
                }

                return 'DOC-001';
            });
        });
    }

    public function test_handover_generate_archives_html_with_user_bound_submitter_signer(): void
    {
        /** @var User $authUser */
        $authUser = User::factory()->create([
            'role' => 'admin',
            'name' => 'Dina Pratama',
            'title_suffix' => 'S.Farm., Apt.',
            'rank' => 'AKP',
            'nrp' => '76112233',
        ]);
        /** @var User $deliveredBy */
        $deliveredBy = User::factory()->create([
            'name' => 'Kuswardani',
            'title_suffix' => 'S.Si., Apt., M.Farm',
            'rank' => 'Kombes Pol.',
            'nrp' => '70040687',
        ]);

        $investigator = Investigator::factory()->create([
            'name' => 'Andri Wibowo',
            'rank' => 'AKP',
            'nrp' => '87010123',
        ]);

        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $authUser->id,
            'status' => 'ready_for_delivery',
        ]);

        Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'W001A2026',
            'short_description' => 'Tablet Putih',
            'test_methods' => json_encode(['gc_ms']),
            'active_substance' => 'MDMA',
            'package_quantity' => 5,
            'unit' => 'tablet',
        ]);

        $delivery = Delivery::factory()->create([
            'request_id' => $request->id,
            'delivered_by' => $deliveredBy->id,
        ]);

        $response = $this->actingAs($authUser)
            ->post(route('delivery.handover.generate', $delivery));

        $response->assertStatus(302);

        $htmlDocument = Document::query()
            ->where('test_request_id', $request->id)
            ->where('document_type', 'ba_penyerahan_html')
            ->latest()
            ->first();

        $this->assertNotNull($htmlDocument);
        Storage::disk('public')->assertExists($htmlDocument->path);

        $htmlContent = Storage::disk('public')->get($htmlDocument->path);

        $expectedName = function_exists('mb_strtoupper')
            ? mb_strtoupper($authUser->display_name_with_title, 'UTF-8')
            : strtoupper($authUser->display_name_with_title);
        $expectedIdentity = function_exists('mb_strtoupper')
            ? mb_strtoupper(trim(($authUser->rank ?? '').' NRP. '.($authUser->nrp ?? '-')), 'UTF-8')
            : strtoupper(trim(($authUser->rank ?? '').' NRP. '.($authUser->nrp ?? '-')));

        $this->assertStringContainsString('Yang Menyerahkan', $htmlContent);
        $this->assertStringContainsString($expectedName, $htmlContent);
        $this->assertStringContainsString($expectedIdentity, $htmlContent);
        $this->assertStringNotContainsString('Staf Laboratorium Farmapol Pusdokkes Polri', $htmlContent);

        $delivery->refresh();
        $this->assertSame($authUser->id, $delivery->delivered_by);
    }

    public function test_handover_regenerate_normalizes_number_and_removes_reference_row(): void
    {
        /** @var User $authUser */
        $authUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $investigator = Investigator::factory()->create([
            'name' => 'Budi Santoso',
            'rank' => 'AKP',
            'nrp' => '87030111',
        ]);

        $request = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $authUser->id,
            'status' => 'ready_for_delivery',
        ]);

        Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'W012A2026',
            'short_description' => 'Serbuk Putih',
            'test_methods' => json_encode(['gc_ms']),
            'package_quantity' => 3,
            'unit' => 'gram',
        ]);

        $delivery = Delivery::factory()->create([
            'request_id' => $request->id,
            'delivered_by' => $authUser->id,
        ]);

        Document::factory()->generated()->create([
            'investigator_id' => $investigator->id,
            'test_request_id' => $request->id,
            'document_type' => 'ba_penyerahan',
            'source' => 'generated',
            'filename' => 'BA-ST/012/II/2026/FPP-ba-penyerahan.pdf',
            'original_filename' => 'BA-ST/012/II/2026/FPP-ba-penyerahan.pdf',
            'path' => 'investigators/test/legacy-ba-penyerahan.pdf',
            'file_path' => 'investigators/test/legacy-ba-penyerahan.pdf',
        ]);

        $this->actingAs($authUser)
            ->post(route('delivery.handover.generate', $delivery))
            ->assertStatus(302);

        $htmlDocument = Document::query()
            ->where('test_request_id', $request->id)
            ->where('document_type', 'ba_penyerahan_html')
            ->latest()
            ->first();

        $this->assertNotNull($htmlDocument);
        Storage::disk('public')->assertExists($htmlDocument->path);

        $htmlContent = Storage::disk('public')->get($htmlDocument->path);

        $this->assertStringContainsString('BA-ST/012/II/2026/FPP', $htmlContent);
        $this->assertStringNotContainsString('BA-ST-012-II-2026-FPP', $htmlContent);
        $this->assertStringNotContainsString('Rujukan Dokumen Asal', $htmlContent);
        $this->assertStringNotContainsString('FR/LPMF/7.8.1', $htmlContent);
    }
}
