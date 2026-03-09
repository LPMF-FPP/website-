<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DummyPengujianSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = \App\Models\User::first() ?? \App\Models\User::factory()->create();

        // Buat 15 TestRequest dengan status on-going dan completed
        for ($i = 0; $i < 15; $i++) {
            $status = fake()->randomElement(['submitted', 'in_testing', 'in_testing', 'ready_for_delivery']);

            $request = \App\Models\TestRequest::factory()->create([
                'status' => $status,
                'received_at' => fake()->dateTimeBetween('-1 month', 'now'),
            ]);

            $sampleCount = rand(2, 6);
            for ($s = 0; $s < $sampleCount; $s++) {
                $sample = \App\Models\Sample::factory()->create([
                    'test_request_id' => $request->id,
                ]);

                if ($status === 'in_testing' || $status === 'ready_for_delivery') {
                    // Beri proses acak untuk sampel
                    $stages = ['preparation', 'instrumentation', 'interpretation'];

                    // Berapa tahap yg sudah selesai?
                    $completedStagesCount = $status === 'ready_for_delivery' ? 3 : rand(0, 3);

                    foreach (array_slice($stages, 0, $completedStagesCount) as $stage) {
                        \App\Models\SampleTestProcess::create([
                            'sample_id' => $sample->id,
                            'stage' => $stage,
                            'performed_by' => $admin->id,
                            'started_at' => fake()->dateTimeBetween('-2 days', '-1 day'),
                            'completed_at' => fake()->dateTimeBetween('-1 day', 'now'),
                        ]);
                    }

                    // Tambahkan 1 proses yg sedang berjalan (jika belum ready_for_delivery dan belum semua stage selesai)
                    if ($status === 'in_testing' && $completedStagesCount < 3 && rand(0, 1) === 1) {
                        \App\Models\SampleTestProcess::create([
                            'sample_id' => $sample->id,
                            'stage' => $stages[$completedStagesCount],
                            'performed_by' => $admin->id,
                            'started_at' => now()->subHours(rand(1, 5)),
                            'completed_at' => null,
                        ]);
                    }
                }
            }
        }
    }
}
