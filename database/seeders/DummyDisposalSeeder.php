<?php

namespace Database\Seeders;

use App\Enums\SampleDisposalStatus;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DummyDisposalSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->first()
            ?? User::factory()->create([
                'name' => 'Admin Disposal Dev',
                'email' => 'admin.disposal.dev@example.com',
                'role' => 'admin-lpmf',
            ]);

        $investigator = Investigator::query()->firstOrCreate(
            ['nrp' => 'DUMMY-DISPOSAL-001'],
            [
                'name' => 'Penyidik Dummy Disposal',
                'rank' => 'AKP',
                'jurisdiction' => 'POLRES DUMMY DISPOSAL',
                'phone' => '081200000901',
                'email' => 'penyidik.disposal@local.test',
                'address' => 'Alamat dummy disposal untuk validasi dev',
                'is_polri' => true,
            ]
        );

        $request = TestRequest::query()->updateOrCreate(
            ['request_number' => 'DUMMY-DISPOSAL-REQ-001'],
            [
                'receipt_number' => 'DUMMY-DISPOSAL-REC-001',
                'investigator_id' => $investigator->id,
                'user_id' => $admin->id,
                'to_office' => 'LPMF FPP',
                'suspect_name' => 'Tersangka Dummy Disposal',
                'suspect_gender' => 'male',
                'suspect_age' => 35,
                'suspect_address' => 'Alamat dummy disposal dev',
                'case_number' => 'LP/DUMMY-DISPOSAL/001/2026',
                'case_description' => 'Permintaan dummy untuk validasi flow pemusnahan sampel di dev.',
                'incident_date' => now()->subDays(140),
                'incident_location' => 'Gudang Dummy Disposal',
                'status' => 'ready_for_delivery',
                'submitted_at' => now()->subDays(135),
                'verified_at' => now()->subDays(134),
                'received_at' => now()->subDays(133),
                'completed_at' => now()->subDays(120),
            ]
        );

        foreach (range(1, 25) as $index) {
            $sampleCode = 'DUM-DSP-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $completedAt = now()->subDays(120 + $index);

            $sample = Sample::query()->updateOrCreate(
                ['sample_code' => $sampleCode],
                [
                    'test_request_id' => $request->id,
                    'short_description' => 'Sampel dummy disposal '.$index,
                    'sample_description' => 'Sampel dummy disposal '.$index.' untuk validasi batch pemusnahan di dev.',
                    'sample_form' => 'powder',
                    'sample_category' => 'obat_keras',
                    'sample_color' => 'putih',
                    'sample_weight' => 1.5,
                    'package_quantity' => 1,
                    'net_weight' => 1.2,
                    'unit' => 'gram',
                    'condition' => 'baik',
                    'received_by' => $admin->id,
                    'received_at' => now()->subDays(133),
                    'sample_status' => 'ready_for_delivery',
                    'testing_started_at' => now()->subDays(125),
                    'testing_completed_at' => $completedAt,
                    'test_methods' => json_encode(['uv_vis']),
                    'requested_test_methods' => json_encode(['uv_vis']),
                    'test_type' => 'Identifikasi UV-VIS',
                    'physical_identification' => 'Serbuk putih dummy disposal '.$index,
                    'batch_number' => 'DUMMY-DSP-BATCH-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                    'expiry_date' => now()->addMonths(12),
                    'quantity' => 1.0,
                    'quantity_unit' => 'gram',
                    'active_substance' => 'Kafein',
                    'notes' => 'Dummy Disposal Seeder',
                    'disposal_status' => SampleDisposalStatus::PENDING,
                    'disposal_id' => null,
                    'disposed_at' => null,
                ]
            );

            SampleTestProcess::query()->updateOrCreate(
                [
                    'sample_id' => $sample->id,
                    'stage' => 'interpretation',
                ],
                [
                    'performed_by' => $admin->id,
                    'started_at' => $completedAt->copy()->subHours(2),
                    'completed_at' => $completedAt,
                    'notes' => 'Dummy Disposal Seeder',
                    'metadata' => [
                        'seed' => 'dummy_disposal',
                        'lhu_number' => 'LHU-DUMMY-DSP-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                    ],
                ]
            );
        }

        $this->command?->info('Dummy disposal seeded: 25 sampel eligible dibuat/diupdate.');
    }
}
