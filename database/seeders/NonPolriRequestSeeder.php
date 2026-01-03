<?php

namespace Database\Seeders;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\Suspect;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NonPolriRequestSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a user
        $user = User::first() ?? User::factory()->create();

        // Create external (non-Polri) investigator with synthetic NRP
        $syntheticNrp = 'EXT-'.strtoupper(Str::random(8));

        $investigator = Investigator::create([
            'is_polri' => false,
            'nrp' => $syntheticNrp,
            'name' => 'Dr. Bambang Supriyanto',
            'rank' => 'NON-POLRI',
            'jurisdiction' => 'Universitas Indonesia',
            'phone' => '081234567890',
            'alt_phone' => '021-7865432',
            'institution' => 'Universitas Indonesia',
            'occupation' => 'Dosen Fakultas Farmasi',
            'folder_key' => $syntheticNrp.'-dr-bambang-supriyanto',
        ]);

        // Create test request
        $testRequest = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'to_office' => 'KaPusdokkes Polri',
            'case_number' => 'REF/UI/2025/001',
            'suspect_name' => 'Andi Wijaya', // First suspect for legacy compatibility
            'suspect_gender' => 'male',
            'suspect_age' => 28,
            'suspect_address' => 'Jl. Margonda Raya No. 100, Depok, Jawa Barat',
            'case_description' => 'Permintaan pengujian sampel untuk keperluan penelitian akademik',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Create 3 suspects
        $suspects = [
            ['name' => 'Andi Wijaya', 'gender' => 'male', 'age' => 28],
            ['name' => 'Siti Rahayu', 'gender' => 'female', 'age' => 25],
            ['name' => 'Budi Hartono', 'gender' => 'male', 'age' => 32],
        ];

        foreach ($suspects as $index => $suspectData) {
            Suspect::create([
                'test_request_id' => $testRequest->id,
                'name' => $suspectData['name'],
                'gender' => $suspectData['gender'],
                'age' => $suspectData['age'],
                'order_no' => $index + 1,
            ]);
        }

        // Create 5 samples
        $samples = [
            [
                'short_description' => 'Serbuk putih dalam kantong plastik',
                'sample_form' => 'powder',
                'active_substance' => 'Methamphetamine',
                'package_quantity' => 10,
                'unit' => 'gram',
            ],
            [
                'short_description' => 'Tablet berwarna pink dengan logo',
                'sample_form' => 'pill', // Changed from 'tablet' to 'pill'
                'active_substance' => 'MDMA',
                'package_quantity' => 25,
                'unit' => 'butir',
            ],
            [
                'short_description' => 'Cairan bening dalam botol kecil',
                'sample_form' => 'liquid',
                'active_substance' => 'Ketamine',
                'package_quantity' => 5,
                'unit' => 'ml',
            ],
            [
                'short_description' => 'Daun kering dalam bungkusan kertas',
                'sample_form' => 'plant',
                'active_substance' => 'Cannabis',
                'package_quantity' => 50,
                'unit' => 'gram',
            ],
            [
                'short_description' => 'Kristal putih dalam wadah plastik',
                'sample_form' => 'crystal', // Changed from 'powder' to 'crystal'
                'active_substance' => 'Cocaine',
                'package_quantity' => 3,
                'unit' => 'gram',
            ],
        ];

        foreach ($samples as $sampleData) {
            Sample::create([
                'test_request_id' => $testRequest->id,
                'short_description' => $sampleData['short_description'],
                'sample_form' => $sampleData['sample_form'],
                'active_substance' => $sampleData['active_substance'],
                'package_quantity' => $sampleData['package_quantity'],
                'unit' => $sampleData['unit'],
                'test_methods' => json_encode(['uv_vis', 'gc_ms']),
                'condition' => 'baik',
                'sample_status' => 'received',
            ]);
        }

        $this->command->info('✅ Created non-Polri request with:');
        $this->command->info("   - Request Number: {$testRequest->request_number}");
        $this->command->info("   - Investigator: {$investigator->name} (NRP: {$investigator->nrp})");
        $this->command->info('   - 3 Suspects');
        $this->command->info('   - 5 Samples');
    }
}
