<?php

namespace Tests\Feature\Process;

use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessShowLhuTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_show_displays_lhu_actions_when_interpretation_lhu_exists(): void
    {
        $this->withoutVite();

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $testRequest = TestRequest::factory()->create([
            'status' => 'in_testing',
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => 'SAMP-LHU-PROCESS',
            'short_description' => 'Sampel dengan LHU siap unduh',
        ]);

        $currentProcess = SampleTestProcess::factory()
            ->interpretation()
            ->completed()
            ->create([
                'sample_id' => $sample->id,
                'metadata' => [
                    'lhu_number' => 'LHU/007/III/2026/FARMAPOL',
                ],
            ]);

        $previewUrl = route('testing.processes.lab-report', $currentProcess);
        $downloadUrl = route('testing.processes.lab-report', [
            'sample_process' => $currentProcess,
            'download' => 1,
        ]);

        $response = $this->actingAs($user)
            ->get(route('testing.show', $testRequest));

        $response->assertOk();
        $response->assertSee('LHU/007/III/2026/FARMAPOL');
        $response->assertSee($previewUrl, false);
        $response->assertSee($downloadUrl, false);
        $response->assertSee('Buka LHU', false);
        $response->assertSee('Unduh LHU', false);
    }

    public function test_testing_show_hides_lhu_actions_when_no_lhu_number_exists(): void
    {
        $this->withoutVite();

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $testRequest = TestRequest::factory()->create([
            'status' => 'in_testing',
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => 'SAMP-NO-LHU-PROCESS',
        ]);

        SampleTestProcess::factory()
            ->interpretation()
            ->completed()
            ->create([
                'sample_id' => $sample->id,
                'metadata' => [],
            ]);

        $response = $this->actingAs($user)
            ->get(route('testing.show', $testRequest));

        $response->assertOk();
        $response->assertDontSee('Buka LHU');
        $response->assertDontSee('Unduh LHU');
    }
}
