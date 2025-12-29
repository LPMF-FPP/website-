<?php

namespace Database\Seeders;

use App\Models\EvidenceUnit;
use App\Models\Investigator;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\TestRequest;
use Illuminate\Database\Seeder;

class LabelTestSeeder extends Seeder
{
    public function run(): void
    {
        // Create or find investigator
        $investigator = Investigator::firstOrCreate(
            ['nrp' => '12345678'],
            [
                'name' => 'John Doe',
                'rank' => 'BRIGADIR',
                'jurisdiction' => 'Polres Jakpus',
                'phone' => '08123456789',
            ]
        );

        // Create test request
        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => 1,
            'case_number' => 'LP/001/XII/2025',
            'status' => 'received',
            'receipt_number' => 'RESI-TEST-001',
            'request_number' => 'REQ-TEST-001',
            'to_office' => 'Ka Lab',
            'suspect_name' => 'Udin',
            'suspect_gender' => 'male',
            'suspect_age' => 30,
            'received_at' => now(),
        ]);

        // Create samples
        $sample1 = Sample::create([
            'test_request_id' => $request->id,
            'sample_name' => 'Sabu Kristal',
            'sample_description' => 'Kristal putih dalam plastik klip',
            'sample_category' => 'narkotika',
            'sample_form' => 'crystal',
            'condition' => 'baik',
            'sample_weight' => 0.5,
            'received_at' => now(),
        ]);

        $sample2 = Sample::create([
            'test_request_id' => $request->id,
            'sample_name' => 'Pil Ekstasi',
            'sample_description' => 'Tablet warna hijau dengan logo',
            'sample_category' => 'psikotropika',
            'sample_form' => 'pill',
            'condition' => 'baik',
            'sample_weight' => 0.3,
            'received_at' => now(),
        ]);

        // Create evidence units
        $eu1 = EvidenceUnit::create([
            'request_id' => $request->id,
            'sample_id' => $sample1->id,
            'receipt_code' => $request->receipt_number,
            'sample_code' => $sample1->sample_code,
            'sample_type' => $sample1->sample_category,
            'sample_desc' => $sample1->sample_description,
            'investigator_name' => $investigator->name,
            'investigator_unit' => $investigator->jurisdiction,
            'seal_status_received' => 'Utuh',
            'condition_received' => 'Baik',
            'received_at' => now(),
            'received_by' => 1,
        ]);

        $eu2 = EvidenceUnit::create([
            'request_id' => $request->id,
            'sample_id' => $sample2->id,
            'receipt_code' => $request->receipt_number,
            'sample_code' => $sample2->sample_code,
            'sample_type' => $sample2->sample_category,
            'sample_desc' => $sample2->sample_description,
            'investigator_name' => $investigator->name,
            'investigator_unit' => $investigator->jurisdiction,
            'seal_status_received' => 'Utuh',
            'condition_received' => 'Baik',
            'received_at' => now(),
            'received_by' => 1,
        ]);

        // Create remaining units for sample 1
        RemainingUnit::create([
            'evidence_unit_id' => $eu1->id,
            'sample_code' => $eu1->sample_code,
            'qty_remaining' => 0.25,
            'uom' => 'gram',
            'seal_status_delivered' => 'Utuh',
            'condition_delivered' => 'Baik',
            'delivered_at' => now(),
            'delivered_by' => 1,
            'handover_doc_no' => 'BA-001/XII/2025',
        ]);

        // Create second remaining for same sample (should be -SISA-2)
        RemainingUnit::create([
            'evidence_unit_id' => $eu1->id,
            'sample_code' => $eu1->sample_code,
            'qty_remaining' => 0.10,
            'uom' => 'gram',
            'seal_status_delivered' => 'Utuh',
            'condition_delivered' => 'Baik',
            'delivered_at' => now(),
            'delivered_by' => 1,
            'handover_doc_no' => 'BA-002/XII/2025',
        ]);

        $this->command->info("Created Request ID: {$request->id}");
        $this->command->info("Sample 1: {$sample1->sample_code}");
        $this->command->info("Sample 2: {$sample2->sample_code}");
        $this->command->info("Evidence Unit 1 QR: {$eu1->qr_content}");
        $this->command->info("Evidence Unit 2 QR: {$eu2->qr_content}");
        
        $rem1 = RemainingUnit::where('evidence_unit_id', $eu1->id)->first();
        $rem2 = RemainingUnit::where('evidence_unit_id', $eu1->id)->skip(1)->first();
        $this->command->info("Remaining 1 Code: {$rem1->remaining_code}");
        $this->command->info("Remaining 2 Code: {$rem2->remaining_code}");
    }
}
