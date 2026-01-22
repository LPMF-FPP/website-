<?php

namespace Database\Seeders;

use App\Models\CustomerSurvey;
use App\Models\Document;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            $this->command->error('Admin user not found. Run: php artisan admin:create first');

            return;
        }

        $this->command->info('=== Creating Test Requests (Permintaan) ===');
        $this->createTestRequests($admin);

        $this->command->info('=== Creating LHU Documents ===');
        $this->createLhuDocuments($admin);

        $this->command->info('=== Creating Customer Surveys ===');
        $this->createCustomerSurveys();

        $this->command->info('=== Creating Inventory (Stok) ===');
        $this->createInventory();

        $this->printSummary();
    }

    private function createTestRequests(User $admin): void
    {
        // Create Investigators
        $investigators = [
            Investigator::firstOrCreate(['nrp' => '76050001'], [
                'name' => 'AKBP Budi Santoso, S.H., M.H.',
                'rank' => 'AKBP',
                'jurisdiction' => 'Polres Metro Jakarta Selatan',
                'phone' => '081234567001',
                'email' => 'budi.santoso@polri.go.id',
                'address' => 'Jl. Fatmawati No. 1, Jakarta Selatan',
            ]),
            Investigator::firstOrCreate(['nrp' => '76050002'], [
                'name' => 'AKP Siti Rahayu, S.H.',
                'rank' => 'AKP',
                'jurisdiction' => 'Polres Metro Jakarta Timur',
                'phone' => '081234567002',
                'email' => 'siti.rahayu@polri.go.id',
                'address' => 'Jl. DI Panjaitan No. 2, Jakarta Timur',
            ]),
            Investigator::firstOrCreate(['nrp' => '76050003'], [
                'name' => 'Iptu Ahmad Wijaya, S.H.',
                'rank' => 'IPTU',
                'jurisdiction' => 'Polres Metro Jakarta Barat',
                'phone' => '081234567003',
                'email' => 'ahmad.wijaya@polri.go.id',
                'address' => 'Jl. S. Parman No. 3, Jakarta Barat',
            ]),
        ];

        $this->command->info('Created '.count($investigators).' investigators');

        $sampleTypes = [
            ['name' => 'Kristal Putih Diduga Sabu', 'form' => 'crystal', 'category' => 'narkotika', 'color' => 'putih bening'],
            ['name' => 'Serbuk Coklat Diduga Heroin', 'form' => 'powder', 'category' => 'narkotika', 'color' => 'coklat'],
            ['name' => 'Daun Kering Diduga Ganja', 'form' => 'plant', 'category' => 'narkotika', 'color' => 'hijau kecoklatan'],
            ['name' => 'Pil Warna-warni Diduga Ekstasi', 'form' => 'pill', 'category' => 'narkotika', 'color' => 'multi'],
            ['name' => 'Cairan Bening Diduga Ketamin', 'form' => 'liquid', 'category' => 'narkotika', 'color' => 'bening'],
        ];

        $statuses = ['submitted', 'verified', 'received', 'in_testing', 'analysis', 'quality_check', 'ready_for_delivery', 'completed', 'rejected'];
        $packagingTypes = ['plastik klip', 'kertas', 'amplop', 'botol'];

        $baseNumber = TestRequest::max('id') ?? 0;

        for ($i = 1; $i <= 10; $i++) {
            $inv = $investigators[array_rand($investigators)];
            $status = $statuses[array_rand($statuses)];
            $reqNum = 'REQ/2025/XII/'.str_pad($baseNumber + $i, 4, '0', STR_PAD_LEFT);

            $request = TestRequest::create([
                'request_number' => $reqNum,
                'investigator_id' => $inv->id,
                'user_id' => $admin->id,
                'suspect_name' => 'Tersangka '.$i,
                'suspect_gender' => rand(0, 1) ? 'male' : 'female',
                'suspect_age' => rand(20, 50),
                'suspect_address' => 'Jl. Contoh No. '.rand(1, 100).', Jakarta',
                'case_number' => 'LP/B/'.rand(100, 999).'/XII/2025/SPKT/'.$inv->jurisdiction,
                'case_description' => 'Perkara dugaan penyalahgunaan narkotika berdasarkan UU No. 35 Tahun 2009',
                'incident_date' => now()->subDays(rand(1, 30)),
                'incident_location' => 'Lokasi Kejadian '.$i.', Jakarta',
                'status' => $status,
                'to_office' => 'LPMF FPP',
                'submitted_at' => now()->subDays(rand(1, 14)),
                'verified_at' => in_array($status, ['verified', 'received', 'in_testing', 'analysis', 'quality_check', 'ready_for_delivery', 'completed']) ? now()->subDays(rand(0, 10)) : null,
                'received_at' => in_array($status, ['received', 'in_testing', 'analysis', 'quality_check', 'ready_for_delivery', 'completed']) ? now()->subDays(rand(0, 7)) : null,
                'completed_at' => $status === 'completed' ? now() : null,
                'rejected_at' => $status === 'rejected' ? now() : null,
                'rejected_reason' => $status === 'rejected' ? 'Permintaan ditolak pada data dummy.' : null,
                'rejected_by' => $status === 'rejected' ? $admin->id : null,
            ]);

            // Add 1-3 samples per request
            $numSamples = rand(1, 3);
            for ($j = 1; $j <= $numSamples; $j++) {
                $type = $sampleTypes[array_rand($sampleTypes)];
                // Use unique sample code with timestamp + random suffix
                $uniqueSuffix = now()->format('His').rand(1000, 9999);
                Sample::create([
                    'test_request_id' => $request->id,
                    'sample_code' => 'BB-'.date('Ymd').'-'.$request->id.'-'.$j.'-'.$uniqueSuffix,
                    'short_description' => $type['name'],
                    'sample_description' => $type['name'].' dalam kemasan plastik',
                    'sample_form' => $type['form'],
                    'sample_category' => $type['category'],
                    'sample_color' => $type['color'],
                    'sample_weight' => number_format(rand(10, 500) / 100, 2, '.', ''),
                    'net_weight' => number_format(rand(10, 450) / 100, 2, '.', ''),
                    'unit' => $packagingTypes[array_rand($packagingTypes)],
                    'condition' => 'baik',
                    // Use status that IkuService recognizes
                    'sample_status' => in_array($status, ['completed', 'ready_for_delivery']) ? 'ready_for_delivery' : 'received',
                    'received_at' => now(),
                    'received_by' => $admin->id,
                    'testing_completed_at' => in_array($status, ['completed', 'ready_for_delivery']) ? now() : null,
                ]);
            }
            $this->command->line('✓ Request '.$request->request_number.' with '.$numSamples.' samples');
        }
    }

    private function createLhuDocuments(User $admin): void
    {
        // Get completed/ready_for_delivery requests that don't have LHU yet
        $completedRequests = TestRequest::whereIn('status', ['completed', 'ready_for_delivery', 'quality_check', 'analysis'])
            ->whereDoesntHave('documents', function ($q) {
                $q->where('document_type', 'lhu');
            })
            ->get();

        $count = 0;
        foreach ($completedRequests as $request) {
            $lhuNumber = 'LHU/'.now()->format('Y').'/'.str_pad($request->id, 4, '0', STR_PAD_LEFT);

            Document::create([
                'investigator_id' => $request->investigator_id,
                'test_request_id' => $request->id,
                'document_type' => 'lhu',
                'source' => 'generated',
                'storage_disk' => 'public',
                'filename' => 'lhu-'.$request->id.'.pdf',
                'original_filename' => $lhuNumber.'.pdf',
                'file_path' => 'documents/lhu/lhu-'.$request->id.'.pdf',
                'path' => 'documents/lhu/lhu-'.$request->id.'.pdf',
                'file_size' => rand(50000, 200000),
                'mime_type' => 'application/pdf',
                'generated_by' => $admin->id,
                'extra' => [
                    'lhu_number' => $lhuNumber,
                    'generated_at' => now()->toDateTimeString(),
                ],
            ]);
            $count++;
            $this->command->line('✓ LHU: '.$lhuNumber);
        }

        $this->command->info('Created '.$count.' LHU documents');
    }

    private function createCustomerSurveys(): void
    {
        // Get completed requests that don't have survey yet
        $requestsNeedSurvey = TestRequest::whereIn('status', ['completed', 'ready_for_delivery'])
            ->whereDoesntHave('customerSurvey')
            ->take(8) // Create surveys for 8 requests
            ->get();

        // Valid enum values from migration
        $jobCategories = ['TNI', 'Polri', 'ASN', 'Swasta', 'Wirausaha', 'Mahasiswa', 'Siswa'];
        $requestTypes = ['Kimia - Fisika', 'Mikrobiologi'];

        $count = 0;
        foreach ($requestsNeedSurvey as $request) {
            // Random satisfaction scores (1-4)
            $answers = [
                'persyaratan' => rand(3, 4),
                'prosedur' => rand(3, 4),
                'ketepatan_waktu' => rand(2, 4),
                'kesesuaian_hasil' => rand(3, 4),
                'kompetensi' => rand(3, 4),
                'sikap' => rand(3, 4),
                'pengaduan' => rand(3, 4),
                'fasilitas' => rand(2, 4),
            ];

            $scoreAvg = array_sum($answers) / count($answers);

            CustomerSurvey::create([
                'test_request_id' => $request->id,
                'respondent_name' => $request->investigator?->name ?? 'Responden Survey '.$request->id,
                'respondent_job_title' => 'Penyidik',
                'respondent_institution' => $request->investigator?->jurisdiction ?? 'Polri',
                'respondent_job_category' => $jobCategories[array_rand($jobCategories)],
                'request_type' => $requestTypes[array_rand($requestTypes)],
                'voluntary_statement' => true,
                'answers' => $answers,
                'suggestion' => 'Semoga pelayanan terus ditingkatkan.',
                'complaint' => rand(0, 1) ? null : 'Tidak ada keluhan.',
                'follow_up' => null,
                'score_avg' => $scoreAvg,
                'submitted_at' => now()->subDays(rand(0, 7)),
                'submitted_by' => null,
            ]);
            $count++;
            $this->command->line('✓ Survey for Request #'.$request->id.' (Score: '.number_format($scoreAvg, 2).')');
        }

        $this->command->info('Created '.$count.' customer surveys');
    }

    private function createInventory(): void
    {
        // Create Locations
        $locations = [
            InventoryLocation::firstOrCreate(['name' => 'Gudang Utama'], ['location_type' => 'warehouse', 'is_restricted' => false]),
            InventoryLocation::firstOrCreate(['name' => 'Lab Kimia'], ['location_type' => 'lab', 'is_restricted' => true]),
            InventoryLocation::firstOrCreate(['name' => 'Cold Storage'], ['location_type' => 'cold_storage', 'is_restricted' => true]),
        ];
        $this->command->info('Created '.count($locations).' locations');

        // Create Inventory Items
        $items = [
            ['type' => 'REAGENT', 'name' => 'Metanol HPLC Grade', 'brand' => 'Merck', 'uom' => 'L', 'storage' => 'RT', 'min' => 5, 'hazardous' => true],
            ['type' => 'REAGENT', 'name' => 'Asam Sulfat p.a.', 'brand' => 'Sigma-Aldrich', 'uom' => 'L', 'storage' => 'RT', 'min' => 2, 'hazardous' => true, 'hazard_class' => 'Corrosive'],
            ['type' => 'REAGENT', 'name' => 'Asetonitril HPLC', 'brand' => 'Honeywell', 'uom' => 'L', 'storage' => 'RT', 'min' => 5, 'hazardous' => true],
            ['type' => 'STANDARD', 'name' => 'Metamfetamin HCl Std', 'brand' => 'Cerilliant', 'uom' => 'mg', 'storage' => '2-8C', 'min' => 100, 'hazardous' => false],
            ['type' => 'STANDARD', 'name' => 'THC Standard', 'brand' => 'Cerilliant', 'uom' => 'mg', 'storage' => '-20C', 'min' => 50, 'hazardous' => false],
            ['type' => 'CONSUMABLE', 'name' => 'Vial 2mL Clear', 'brand' => 'Agilent', 'uom' => 'pcs', 'storage' => 'RT', 'min' => 100, 'hazardous' => false],
            ['type' => 'CONSUMABLE', 'name' => 'Syringe Filter 0.45um', 'brand' => 'Whatman', 'uom' => 'pcs', 'storage' => 'RT', 'min' => 50, 'hazardous' => false],
            ['type' => 'CONSUMABLE', 'name' => 'Pipette Tips 1000uL', 'brand' => 'Eppendorf', 'uom' => 'box', 'storage' => 'RT', 'min' => 10, 'hazardous' => false],
        ];

        foreach ($items as $itemData) {
            $item = InventoryItem::firstOrCreate(
                ['name' => $itemData['name'], 'brand' => $itemData['brand']],
                [
                    'item_type' => $itemData['type'],
                    'manufacturer' => $itemData['brand'],
                    'specification' => $itemData['name'].' untuk analisis laboratorium',
                    'uom' => $itemData['uom'],
                    'pack_size' => 1,
                    'is_hazardous' => $itemData['hazardous'],
                    'hazard_class' => $itemData['hazard_class'] ?? null,
                    'storage_condition' => $itemData['storage'],
                    'min_stock' => $itemData['min'],
                    'is_active' => true,
                ]
            );

            // Create 1-2 lots per item
            $numLots = rand(1, 2);
            for ($l = 1; $l <= $numLots; $l++) {
                $lot = InventoryLot::firstOrCreate(
                    ['item_id' => $item->id, 'lot_no' => 'LOT-'.date('Ym').'-'.str_pad($item->id * 10 + $l, 4, '0', STR_PAD_LEFT)],
                    [
                        'expiry_date' => now()->addMonths(rand(6, 24)),
                        'received_date' => now()->subDays(rand(1, 90)),
                        'status' => 'ACTIVE',
                        'notes' => 'Lot diterima dari supplier',
                    ]
                );

                // Create balance in random location
                $location = $locations[array_rand($locations)];
                InventoryBalance::updateOrCreate(
                    ['item_id' => $item->id, 'lot_id' => $lot->id, 'location_id' => $location->id],
                    [
                        'on_hand_qty' => rand(10, 100),
                        'reserved_qty' => rand(0, 10),
                        'updated_at' => now(),
                    ]
                );
            }
            $this->command->line('✓ Item: '.$item->name.' ('.$numLots.' lots)');
        }
    }

    private function printSummary(): void
    {
        $this->command->newLine();
        $this->command->info('=== Summary ===');
        $this->command->line('Investigators: '.Investigator::count());
        $this->command->line('Test Requests: '.TestRequest::count());
        $this->command->line('Samples: '.Sample::count());
        $this->command->line('LHU Documents: '.Document::where('document_type', 'lhu')->count());
        $this->command->line('Customer Surveys: '.CustomerSurvey::count());
        $this->command->line('Inventory Items: '.InventoryItem::count());
        $this->command->line('Inventory Lots: '.InventoryLot::count());
        $this->command->line('Inventory Balances: '.InventoryBalance::count());
        $this->command->newLine();
        $this->command->info('✅ Dummy data created successfully!');

        // Show IKU-related counts
        $this->command->newLine();
        $this->command->info('=== IKU Data Verification ===');
        $this->command->line('A (requests completed): '.TestRequest::whereIn('status', ['completed', 'ready_for_delivery'])->count());
        $this->command->line('B (requests submitted): '.TestRequest::whereNotNull('submitted_at')->count());
        $this->command->line('C (samples tested): '.Sample::whereIn('sample_status', ['tested', 'completed'])->count());
        $this->command->line('E (LHU issued): '.Document::where('document_type', 'lhu')->count());
        $this->command->line('F (surveys received): '.CustomerSurvey::count());
    }
}
