<?php

namespace Tests\Browser\Labels;

use App\Models\EvidenceUnit;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LabelManagementTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_view_labels_section(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);
        $sample = Sample::factory()->create(['test_request_id' => $request->id]);
        
        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->assertSee('Label Barang Bukti')
                ->assertSee('Generate Label');
        });
    }

    public function test_user_can_generate_evidence_label(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);
        $sample = Sample::factory()->create(['test_request_id' => $request->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Generate Label')
                ->click('#btn-generate-label')
                ->waitForText('Ya, Buat Label')
                ->press('Ya, Buat Label')
                ->waitForText('Cetak Sheet', 10) // Wait for reload and text
                ->assertSee('Cetak Sheet');
        });
    }

    public function test_user_can_print_label(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);
        $sample = Sample::factory()->create(['test_request_id' => $request->id]);
        
        EvidenceUnit::create([
            'request_id' => $request->id,
            'sample_id' => $sample->id,
            'sample_code' => $sample->sample_code,
            'receipt_code' => 'REC-001',
            'sample_type' => 'Drugs',
            'sample_desc' => 'White powder',
            'received_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Cetak Sheet')
                ->assertPresent('a:contains("Cetak Sheet")');
        });
    }

    public function test_user_can_scan_barcode(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);
        
        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit('/labels/scan')
                ->waitForText('Scan Barcode')
                ->assertPresent('input[name="barcode"]')
                ->type('barcode', $request->request_number)
                ->keys('input[name="barcode"]', '{enter}')
                ->waitForText($request->case_number)
                ->assertSee($request->case_number);
        });
    }
}
