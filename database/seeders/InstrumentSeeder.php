<?php

namespace Database\Seeders;

use App\Models\Instrument;
use App\Models\MethodInstrumentRequirement;
use Illuminate\Database\Seeder;

class InstrumentSeeder extends Seeder
{
    public function run(): void
    {
        $instruments = [
            ['code' => 'CENTRIFUGE', 'name' => 'Centrifuge', 'category' => 'prep', 'is_active' => true],
            ['code' => 'SONICATOR', 'name' => 'Sonicator', 'category' => 'prep', 'is_active' => true],
            ['code' => 'VORTEX', 'name' => 'Vortex Mixer', 'category' => 'prep', 'is_active' => true],
            ['code' => 'ANALYTICAL_BALANCE', 'name' => 'Analytical Balance', 'category' => 'prep', 'is_active' => true],
            ['code' => 'UV_VIS', 'name' => 'UV-VIS Spectrophotometer', 'category' => 'analytical', 'is_active' => true],
            ['code' => 'GC_MS', 'name' => 'GC-MS (Gas Chromatography Mass Spectrometry)', 'category' => 'analytical', 'is_active' => true],
            ['code' => 'LC_MS', 'name' => 'LC-MS (Liquid Chromatography Mass Spectrometry)', 'category' => 'analytical', 'is_active' => true],
            ['code' => 'HPLC', 'name' => 'HPLC (High Performance Liquid Chromatography)', 'category' => 'analytical', 'is_active' => true],
        ];

        foreach ($instruments as $data) {
            Instrument::updateOrCreate(['code' => $data['code']], $data);
        }

        $this->command->info('Instruments seeded successfully.');
        $this->seedDefaultRequirements();
    }

    protected function seedDefaultRequirements(): void
    {
        if (MethodInstrumentRequirement::count() > 0) {
            $this->command->info('Method instrument requirements already exist, skipping defaults.');

            return;
        }

        $instrumentMap = Instrument::pluck('id', 'code')->toArray();

        $requirements = [
            ['method_code' => 'uv_vis', 'instrument_code' => 'UV_VIS', 'mandatory' => true, 'usage_type' => 'RUN', 'sequence' => 1],
            ['method_code' => 'gc_ms', 'instrument_code' => 'CENTRIFUGE', 'mandatory' => true, 'usage_type' => 'PREP', 'sequence' => 1],
            ['method_code' => 'gc_ms', 'instrument_code' => 'SONICATOR', 'mandatory' => true, 'usage_type' => 'PREP', 'sequence' => 2],
            ['method_code' => 'gc_ms', 'instrument_code' => 'GC_MS', 'mandatory' => true, 'usage_type' => 'RUN', 'sequence' => 3],
            ['method_code' => 'lc_ms', 'instrument_code' => 'CENTRIFUGE', 'mandatory' => true, 'usage_type' => 'PREP', 'sequence' => 1],
            ['method_code' => 'lc_ms', 'instrument_code' => 'LC_MS', 'mandatory' => true, 'usage_type' => 'RUN', 'sequence' => 2],
        ];

        foreach ($requirements as $req) {
            $instrumentId = $instrumentMap[$req['instrument_code']] ?? null;
            if ($instrumentId) {
                MethodInstrumentRequirement::create([
                    'method_code' => $req['method_code'],
                    'instrument_id' => $instrumentId,
                    'mandatory' => $req['mandatory'],
                    'usage_type' => $req['usage_type'],
                    'sequence' => $req['sequence'],
                ]);
            }
        }

        $this->command->info('Default method instrument requirements seeded successfully.');
    }
}
