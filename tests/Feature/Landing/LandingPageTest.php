<?php

namespace Tests\Feature\Landing;

use App\Models\CustomerSurvey;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\ChangelogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_page_uses_real_logo_and_operational_data(): void
    {
        User::factory()->create([
            'is_active' => true,
        ]);

        $activeRequest = TestRequest::factory()->create([
            'status' => 'in_testing',
        ]);

        $completedRequest = TestRequest::factory()->create([
            'status' => 'completed',
            'submitted_at' => now()->subHours(12),
            'ready_for_delivery_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);

        Sample::factory()->create([
            'test_request_id' => $activeRequest->id,
        ]);

        Sample::factory()->create([
            'test_request_id' => $completedRequest->id,
        ]);

        CustomerSurvey::query()->create([
            'test_request_id' => $completedRequest->id,
            'respondent_name' => 'Responden Dummy',
            'respondent_job_title' => 'Penyidik',
            'respondent_institution' => 'POLRES Dummy',
            'respondent_job_category' => 'Polri',
            'request_type' => 'Kimia - Fisika',
            'voluntary_statement' => true,
            'answers' => [],
            'score_avg' => 4,
            'complaint' => '-',
            'follow_up' => '-',
            'suggestion' => 'Pelayanan sangat baik',
            'submitted_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('images/logo-pusdokkes-polri.png', false);
        $response->assertSee('1 resi aktif', false);
        $response->assertSee('2', false);
        $response->assertSee('hari/permintaan', false);
        $response->assertSee('4,00/4', false);
        $response->assertSee('1', false);
        $response->assertSee('Data Operasional Tersedia', false);
        $response->assertSee('LPMF LIMS', false);
        $response->assertSee('Masukan nomor resi', false);
        $latestVersion = app(ChangelogService::class)->getChangelogs()[0]['version'] ?? null;

        $response->assertSee('Ver. '.$latestVersion, false);
    }
}
