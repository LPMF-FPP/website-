<?php

namespace Tests\Feature\Labels;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\LabelService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvidenceSheetPrintTest extends TestCase
{
    use DatabaseTransactions;

    public function test_printing_evidence_sheet_syncs_missing_labels_for_all_request_samples(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create();

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $samples = collect(range(1, 6))->map(function (int $index) use ($testRequest) {
            return Sample::factory()->create([
                'test_request_id' => $testRequest->id,
                'sample_code' => 'SAMP-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'short_description' => 'Sampel uji '.$index,
            ]);
        });

        /** @var LabelService $labelService */
        $labelService = app(LabelService::class);
        $labelService->createEvidenceUnits($testRequest->id, $samples->take(5)->pluck('id')->all());

        $capturedPages = null;

        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('pdf-content');
        $mockPdf->shouldReceive('stream')->andReturn(response('pdf-content', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$capturedPages) {
                $this->assertSame('labels.evidence-sheet', $view);
                $capturedPages = $data['pages'] ?? null;

                return true;
            })
            ->andReturn($mockPdf);

        $response = $this->actingAs($user)
            ->get(route('labels.evidence.sheet', $testRequest->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertNotNull($capturedPages);
        /** @var \Illuminate\Support\Collection<int, array{layout: string, rows: \Illuminate\Support\Collection<int, array{left: array<string, mixed>|null, right: array<string, mixed>|null}>}> $capturedPages */
        $this->assertCount(2, $capturedPages);
        $this->assertSame(6, $labelService->getEvidenceUnitsForRequest($testRequest->id)->count());
        $this->assertSame('mixed', $capturedPages->first()['layout']);
        $this->assertSame('evidence-grid', $capturedPages->last()['layout']);
        $this->assertSame('SAMP-005', $capturedPages->last()['rows']->first()['left']['kode_sampel']);
        $this->assertSame('SAMP-006', $capturedPages->last()['rows']->first()['right']['kode_sampel']);
    }

    public function test_printing_evidence_sheet_only_compacts_contiguous_sample_codes_with_same_prefix(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create();

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        foreach (['ABC-001', 'ABC-003', 'XYZ-004', 'XYZ-006', 'ZZZ-010'] as $code) {
            Sample::factory()->create([
                'test_request_id' => $testRequest->id,
                'sample_code' => $code,
                'short_description' => 'Sampel uji '.$code,
            ]);
        }

        app(LabelService::class)->ensureEvidenceUnitsForRequest($testRequest->id);

        $capturedPages = null;

        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('pdf-content');
        $mockPdf->shouldReceive('stream')->andReturn(response('pdf-content', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$capturedPages) {
                $this->assertSame('labels.evidence-sheet', $view);
                $capturedPages = $data['pages'] ?? null;

                return true;
            })
            ->andReturn($mockPdf);

        $response = $this->actingAs($user)
            ->get(route('labels.evidence.sheet', $testRequest->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertNotNull($capturedPages);
        $this->assertSame(
            '5 sampel',
            $capturedPages->first()['rows']->first()['right']['daftar_kode_sampel']
        );
    }

    public function test_printing_evidence_sheet_compacts_contiguous_sample_codes_with_same_prefix(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create();

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        foreach (range(1, 5) as $index) {
            Sample::factory()->create([
                'test_request_id' => $testRequest->id,
                'sample_code' => 'ABC-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'short_description' => 'Sampel uji '.$index,
            ]);
        }

        app(LabelService::class)->ensureEvidenceUnitsForRequest($testRequest->id);

        $capturedPages = null;

        $mockPdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('setPaper')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('pdf-content');
        $mockPdf->shouldReceive('stream')->andReturn(response('pdf-content', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$capturedPages) {
                $this->assertSame('labels.evidence-sheet', $view);
                $capturedPages = $data['pages'] ?? null;

                return true;
            })
            ->andReturn($mockPdf);

        $response = $this->actingAs($user)
            ->get(route('labels.evidence.sheet', $testRequest->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertNotNull($capturedPages);
        $this->assertSame(
            'ABC-001-005 (5 sampel)',
            $capturedPages->first()['rows']->first()['right']['daftar_kode_sampel']
        );
    }

    public function test_printing_evidence_sheet_renders_follow_up_page_for_more_than_five_labels(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create();

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $prefix = 'TEST-'.strtoupper(Str::random(6)).'-';

        $samples = collect(range(1, 10))->map(function (int $index) use ($testRequest, $prefix) {
            return Sample::factory()->create([
                'test_request_id' => $testRequest->id,
                'sample_code' => $prefix.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'short_description' => 'Sampel dummy ke-'.$index,
            ]);
        });

        app(LabelService::class)->createEvidenceUnits($testRequest->id, $samples->pluck('id')->all());

        $response = $this->actingAs($user)
            ->get(route('labels.evidence.sheet', $testRequest->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $pdfText = $this->extractPdfText($response->getContent());

        $this->assertStringContainsString($prefix.'001', $pdfText);
        $this->assertStringContainsString($prefix.'005', $pdfText);
        $this->assertStringContainsString($prefix.'006', $pdfText);
        $this->assertStringContainsString($prefix.'010', $pdfText);
    }

    private function extractPdfText(string $pdfContent): string
    {
        $pdfPath = tempnam(sys_get_temp_dir(), 'evidence-sheet-');
        $txtPath = tempnam(sys_get_temp_dir(), 'evidence-sheet-text-');

        if ($pdfPath === false || $txtPath === false) {
            $this->fail('Gagal menyiapkan file sementara untuk verifikasi PDF.');
        }

        file_put_contents($pdfPath, $pdfContent);

        $command = sprintf(
            'pdftotext %s %s',
            escapeshellarg($pdfPath),
            escapeshellarg($txtPath)
        );

        exec($command, $output, $exitCode);

        try {
            $this->assertSame(0, $exitCode, 'pdftotext gagal mengekstrak isi PDF untuk verifikasi test.');

            $text = file_get_contents($txtPath);

            $this->assertNotFalse($text, 'Gagal membaca hasil ekstraksi teks PDF.');

            return Str::of($text)->replace("\f", "\n")->toString();
        } finally {
            @unlink($pdfPath);
            @unlink($txtPath);
        }
    }
}
