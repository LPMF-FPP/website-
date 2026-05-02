<?php

namespace Tests\Feature\Delivery;

use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveryShowLhuTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_show_displays_lhu_number_and_pdf_link_per_sample_when_available(): void
    {
        $this->seed(SystemSettingSeeder::class);
        settings_fake(['notifications.whatsapp.enabled' => false]);
        settings_forget_cache();

        Queue::fake();

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready_for_delivery',
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'SAMP-LHU-TEST',
            'short_description' => 'Sampel uji untuk LHU',
            'status' => 'ready_for_delivery',
            // Prevent DeliveryController::autoGenerateRemainingLabels() from creating extra rows.
            'package_quantity' => 0,
            'quantity' => 0,
        ]);

        $process = SampleTestProcess::factory()
            ->interpretation()
            ->completed()
            ->create([
                'sample_id' => $sample->id,
                'metadata' => ['lhu_number' => 'LHU/001/II/2026/FARMAPOL'],
            ]);

        $url = route('testing.processes.lab-report', $process);

        $response = $this->actingAs($user)
            ->get(route('delivery.show', $request));

        $response->assertOk();
        $response->assertSee('LHU/001/II/2026/FARMAPOL');
        $response->assertSee($url, false);
        $response->assertSee('href="'.$url.'"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
        $response->assertSee('Buka PDF', false);
        $response->assertSee('Laporan Hasil Uji LHU/001/II/2026/FARMAPOL', false);
    }

    public function test_delivery_show_does_not_display_lhu_row_when_interpretation_process_has_no_lhu_number(): void
    {
        $this->seed(SystemSettingSeeder::class);
        settings_fake(['notifications.whatsapp.enabled' => false]);
        settings_forget_cache();

        Queue::fake();

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready_for_delivery',
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'SAMP-LHU-NO-META',
            'short_description' => 'Sampel tanpa nomor LHU',
            'status' => 'ready_for_delivery',
            // Prevent DeliveryController::autoGenerateRemainingLabels() from creating extra rows.
            'package_quantity' => 0,
            'quantity' => 0,
        ]);

        SampleTestProcess::factory()
            ->interpretation()
            ->completed()
            ->create([
                'sample_id' => $sample->id,
                'metadata' => [],
            ]);

        $response = $this->actingAs($user)
            ->get(route('delivery.show', $request));

        $response->assertOk();
        $response->assertDontSee('Buka PDF');
        $response->assertDontSee('Laporan Hasil Uji', false);
    }

    public function test_delivery_show_does_not_display_lhu_row_when_interpretation_process_is_missing(): void
    {
        $this->seed(SystemSettingSeeder::class);
        settings_fake(['notifications.whatsapp.enabled' => false]);
        settings_forget_cache();

        Queue::fake();

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready_for_delivery',
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'SAMP-NO-INTERP',
            'short_description' => 'Sampel tanpa proses interpretasi',
            'status' => 'ready_for_delivery',
            // Prevent DeliveryController::autoGenerateRemainingLabels() from creating extra rows.
            'package_quantity' => 0,
            'quantity' => 0,
        ]);

        SampleTestProcess::factory()
            ->preparation()
            ->completed()
            ->create([
                'sample_id' => $sample->id,
            ]);

        $response = $this->actingAs($user)
            ->get(route('delivery.show', $request));

        $response->assertOk();
        $response->assertDontSee('Buka PDF');
        $response->assertDontSee('Laporan Hasil Uji', false);
    }

    public function test_delivery_show_displays_request_and_expert_witness_letter_details(): void
    {
        $this->seed(SystemSettingSeeder::class);
        settings_fake(['notifications.whatsapp.enabled' => false]);
        settings_forget_cache();

        Queue::fake();

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready_for_delivery',
            'case_number' => 'B/123/V/2026/Reskrim',
            'letter_date' => '2026-05-08',
            'has_expert_witness_request' => true,
            'expert_witness_letter_number' => 'SAHLI/456/V/2026',
            'expert_witness_letter_date' => '2026-05-09',
        ]);

        Sample::factory()->create([
            'test_request_id' => $request->id,
            'status' => 'ready_for_delivery',
            'package_quantity' => 0,
            'quantity' => 0,
        ]);

        $response = $this->actingAs($user)
            ->get(route('delivery.show', $request));

        $response->assertOk();
        $response->assertSee('Dokumen Permintaan');
        $response->assertSee('Nomor Surat Permintaan');
        $response->assertSee('B/123/V/2026/Reskrim');
        $response->assertSee('Tanggal Surat Permintaan');
        $response->assertSee('08 Mei 2026');
        $response->assertSee('Surat Saksi Ahli');
        $response->assertSee('SAHLI/456/V/2026');
        $response->assertSee('09 Mei 2026');
    }
}
