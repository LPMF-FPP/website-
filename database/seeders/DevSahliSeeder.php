<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\ExpertWitnessDocument;
use App\Models\ExpertWitnessRequest;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\TestResult;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DevSahliSeeder extends Seeder
{
    public const EMAIL = 'sahli.dev@example.test';

    public const PASSWORD = 'SahliDev!2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'User Dev Sahli',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $investigator = Investigator::updateOrCreate(
            ['nrp' => 'DEV-SAHLI-001'],
            [
                'name' => 'Penyidik Demo Sahli',
                'rank' => 'AKP',
                'jurisdiction' => 'POLRES DEMO',
                'phone' => '081234567890',
                'is_polri' => true,
            ]
        );

        $external = ExpertWitnessRequest::updateOrCreate(
            ['submission_token' => '00000000-0000-4000-8000-000000000001'],
            [
                'source' => ExpertWitnessRequest::SOURCE_EXTERNAL,
                'submitted_by' => $user->id,
                'letter_number' => 'B/DEV-LUAR/IX/2026',
                'letter_date' => '2026-09-01',
                'investigator_name' => 'Penyidik Lab Luar Demo',
                'investigator_institution' => 'POLRES DEMO',
                'investigator_phone' => '081298765432',
                'notes' => 'Data dummy development untuk pengujian alur lab luar.',
                'submitted_at' => now()->subDays(2),
            ]
        );
        $this->seedMilestones($external, 1);
        $this->seedSubmissionLetter($external);

        $testRequest = TestRequest::firstOrCreate(
            ['case_description' => 'DEV-SAHLI-FARMAPOL-001'],
            [
                'investigator_id' => $investigator->id,
                'user_id' => $user->id,
                'case_number' => 'DEV/SAHLI/001',
                'letter_date' => '2026-08-28',
                'suspect_name' => 'Tersangka Demo Sahli',
                'status' => 'completed',
                'has_expert_witness_request' => true,
                'expert_witness_letter_number' => 'B/DEV-FP/IX/2026',
                'expert_witness_letter_date' => '2026-08-28',
                'submitted_at' => now()->subDays(5),
            ]
        );

        $sampleOne = $this->seedSample($testRequest, 'DEV-SAMP-001', 'Serbuk putih dalam plastik klip', 'LHU/DEV/001/2026', 'Positif mengandung zat uji demo');
        $this->seedSample($testRequest, 'DEV-SAMP-002', 'Tablet putih tanpa kemasan', 'LHU/DEV/002/2026', 'Negatif terhadap zat uji demo');

        $farmapol = ExpertWitnessRequest::updateOrCreate(
            ['test_request_id' => $testRequest->id],
            [
                'source' => ExpertWitnessRequest::SOURCE_FARMAPOL,
                'investigator_id' => $investigator->id,
                'submitted_by' => $user->id,
                'letter_number' => 'B/DEV-FP/IX/2026',
                'letter_date' => '2026-08-28',
                'investigator_name' => $investigator->name,
                'investigator_institution' => $investigator->jurisdiction,
                'investigator_phone' => $investigator->phone,
                'submitted_at' => $testRequest->created_at,
            ]
        );
        $this->seedMilestones($farmapol, 5);

        $this->command?->info('Dev Sahli user: '.self::EMAIL);
        $this->command?->info('Dev Sahli password: '.self::PASSWORD);
        $this->command?->info('Dev Sahli data: 1 lab luar dan 1 Farmapol dengan 2 sampel/LHU.');
    }

    private function seedSample(TestRequest $request, string $code, string $description, string $lhuNumber, string $conclusion): Sample
    {
        $sample = Sample::updateOrCreate(
            ['sample_code' => $code],
            [
                'test_request_id' => $request->id,
                'short_description' => $description,
                'sample_description' => $description,
                'sample_status' => 'tested',
            ]
        );

        SampleTestProcess::updateOrCreate(
            ['sample_id' => $sample->id, 'stage' => 'interpretation'],
            [
                'completed_at' => now()->subDays(3),
                'metadata' => ['lhu_number' => $lhuNumber],
            ]
        );

        TestResult::updateOrCreate(
            ['sample_id' => $sample->id],
            [
                'tested_by' => $request->user_id,
                'test_method' => 'Metode uji demo',
                'equipment_used' => 'Instrumen demo',
                'active_substances' => [],
                'test_conclusion' => $conclusion,
                'result_status' => str_starts_with($conclusion, 'Positif') ? 'positive' : 'negative',
                'qc_approved' => true,
                'reviewed_at' => now()->subDays(2),
                'reviewed_by' => $request->user_id,
            ]
        );

        Document::updateOrCreate(
            [
                'test_request_id' => $request->id,
                'sample_id' => $sample->id,
                'document_type' => 'laporan_hasil_uji',
            ],
            [
                'investigator_id' => $request->investigator_id,
                'source' => 'generated',
                'storage_disk' => 'public',
                'filename' => $lhuNumber.'.pdf',
                'original_filename' => $lhuNumber.'.pdf',
                'file_path' => 'generated/'.$lhuNumber.'.pdf',
                'path' => 'generated/'.$lhuNumber.'.pdf',
                'file_size' => 0,
                'mime_type' => 'application/pdf',
            ]
        );

        return $sample;
    }

    private function seedMilestones(ExpertWitnessRequest $request, int $completedCount): void
    {
        foreach (array_keys(ExpertWitnessRequest::MILESTONES) as $index => $code) {
            $request->milestones()->updateOrCreate(
                ['code' => $code],
                [
                    'completed_at' => $index < $completedCount ? now()->subDays(4 - $index) : null,
                    'completed_by' => $index < $completedCount ? $request->submitted_by : null,
                ]
            );
        }

        $request->update([
            'completed_at' => $completedCount === count(ExpertWitnessRequest::MILESTONES) ? now()->subDay() : null,
        ]);
    }

    private function seedSubmissionLetter(ExpertWitnessRequest $request): void
    {
        $path = 'expert-witness/requests/'.$request->id.'/dev-submission-letter.pdf';
        Storage::disk('local')->put($path, $this->dummyPdf());

        ExpertWitnessDocument::updateOrCreate(
            ['expert_witness_request_id' => $request->id, 'document_type' => 'submission_letter'],
            [
                'disk' => 'local',
                'path' => $path,
                'original_filename' => 'surat-pengajuan-sahli-dev.pdf',
                'file_size' => Storage::disk('local')->size($path),
                'mime_type' => 'application/pdf',
            ]
        );
    }

    private function dummyPdf(): string
    {
        $stream = 'BT /F1 18 Tf 72 720 Td (Surat Pengajuan Sahli DEV) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n \n', $offset);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
