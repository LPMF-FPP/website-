<?php

namespace Database\Seeders;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DummyPengujianSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::where('role', 'admin-lpmf')->first()
            ?? User::factory()->create([
                'email' => 'admin@example.com',
                'name' => 'Admin QA',
                'role' => 'admin-lpmf',
            ]);

        $investigator = Investigator::firstOrCreate(
            ['nrp' => 'DUMMY-PENYIDIK-001'],
            [
                'name' => 'Penyidik Dummy Lokal',
                'rank' => 'IPTU',
                'jurisdiction' => 'POLRES DUMMY LOKAL',
                'phone' => '081200000001',
                'email' => 'penyidik.dummy@local.test',
                'address' => 'Alamat dummy lokal untuk pengujian UI',
                'is_polri' => true,
            ]
        );

        $dataset = [
            [
                'request_number' => 'DUMMY-PENGUJIAN-001',
                'receipt_number' => 'DUMMY-RECEIPT-001',
                'status' => 'in_testing',
                'suspect_name' => 'Dummy Pengujian A',
                'samples' => [
                    ['code' => 'DUM-SAMP-001', 'short' => 'Serbuk putih dummy validasi workflow', 'stage_state' => 'current_preparation'],
                    ['code' => 'DUM-SAMP-002', 'short' => 'Tablet dummy dengan tahap instrumen aktif', 'stage_state' => 'current_instrumentation'],
                ],
            ],
            [
                'request_number' => 'DUMMY-PENGUJIAN-002',
                'receipt_number' => 'DUMMY-RECEIPT-002',
                'status' => 'ready_for_delivery',
                'suspect_name' => 'Dummy Pengujian B',
                'samples' => [
                    ['code' => 'DUM-SAMP-003', 'short' => 'Kristal dummy siap diserahkan', 'stage_state' => 'ready_for_delivery'],
                    ['code' => 'DUM-SAMP-004', 'short' => 'Cairan dummy siap diserahkan', 'stage_state' => 'ready_for_delivery'],
                    ['code' => 'DUM-SAMP-005', 'short' => 'Daun dummy siap diserahkan', 'stage_state' => 'ready_for_delivery'],
                ],
            ],
        ];

        foreach ($dataset as $index => $requestData) {
            $request = TestRequest::updateOrCreate(
                ['request_number' => $requestData['request_number']],
                [
                    'receipt_number' => $requestData['receipt_number'],
                    'investigator_id' => $investigator->id,
                    'user_id' => $admin->id,
                    'to_office' => 'LPMF FPP',
                    'suspect_name' => $requestData['suspect_name'],
                    'suspect_gender' => $index % 2 === 0 ? 'male' : 'female',
                    'suspect_age' => 30 + $index,
                    'suspect_address' => 'Data dummy lokal untuk validasi pengujian',
                    'case_number' => 'DUMMY/CASE/'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'case_description' => 'Permintaan dummy untuk validasi tampilan dan alur pengujian lokal.',
                    'incident_date' => now()->subDays(5 + $index),
                    'incident_location' => 'Laboratorium Dummy Lokal',
                    'status' => $requestData['status'],
                    'submitted_at' => now()->subDays(4 + $index),
                    'verified_at' => now()->subDays(3 + $index),
                    'received_at' => now()->subDays(2 + $index),
                    'ready_for_delivery_at' => $requestData['status'] === 'ready_for_delivery' ? now()->subDay() : null,
                    'completed_at' => null,
                    'rejected_reason' => null,
                    'rejected_at' => null,
                    'rejected_by' => null,
                ]
            );

            foreach ($requestData['samples'] as $sampleIndex => $sampleData) {
                $sample = Sample::updateOrCreate(
                    ['sample_code' => $sampleData['code']],
                    [
                        'test_request_id' => $request->id,
                        'short_description' => $sampleData['short'],
                        'sample_description' => $sampleData['short'].' untuk pengujian lokal.',
                        'sample_form' => 'powder',
                        'sample_category' => 'narkotika',
                        'sample_color' => 'putih',
                        'sample_weight' => 1.25 + $sampleIndex,
                        'package_quantity' => 1,
                        'net_weight' => 1.00 + $sampleIndex,
                        'unit' => 'gram',
                        'condition' => 'baik',
                        'received_by' => $admin->id,
                        'received_at' => now()->subDays(2),
                        'sample_status' => $requestData['status'] === 'ready_for_delivery' ? 'ready_for_delivery' : 'in_testing',
                        'testing_started_at' => now()->subDay(),
                        'testing_completed_at' => $requestData['status'] === 'ready_for_delivery' ? now()->subHours(6) : null,
                        'notes' => 'Dummy Pengujian Seeder',
                    ]
                );

                $this->syncProcesses($sample, $admin->id, $sampleData['stage_state']);
            }
        }

        $this->command->info('Dummy pengujian seeded for local/testing admin.');
    }

    private function syncProcesses(Sample $sample, int $adminId, string $stageState): void
    {
        $stages = ['preparation', 'instrumentation', 'interpretation'];

        foreach ($stages as $position => $stage) {
            $attributes = match ($stageState) {
                'current_preparation' => $stage === 'preparation'
                    ? ['started_at' => now()->subHours(2), 'completed_at' => null]
                    : null,
                'current_instrumentation' => match ($stage) {
                    'preparation' => ['started_at' => now()->subDays(1), 'completed_at' => now()->subHours(8)],
                    'instrumentation' => ['started_at' => now()->subHours(3), 'completed_at' => null],
                    default => null,
                },
                'ready_for_delivery' => [
                    'started_at' => now()->subDays(2 - min($position, 1)),
                    'completed_at' => now()->subHours(12 - ($position * 2)),
                ],
                default => null,
            };

            if ($attributes === null) {
                SampleTestProcess::where('sample_id', $sample->id)->where('stage', $stage)->delete();

                continue;
            }

            SampleTestProcess::updateOrCreate(
                ['sample_id' => $sample->id, 'stage' => $stage],
                [
                    'performed_by' => $adminId,
                    'started_at' => $attributes['started_at'],
                    'completed_at' => $attributes['completed_at'],
                    'notes' => 'Dummy Pengujian Seeder',
                    'metadata' => ['seed' => 'dummy_pengujian'],
                ]
            );
        }
    }
}
