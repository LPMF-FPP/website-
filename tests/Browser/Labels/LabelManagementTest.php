<?php

namespace Tests\Browser\Labels;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LabelManagementTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_view_labels_page(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/labels/evidence/request/{$request->id}/sheet")
                ->assertSee('Evidence Labels')
                ->assertSee($request->request_number);
        });
    }

    public function test_user_can_generate_evidence_label(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/labels/evidence/request/{$request->id}/sheet")
                ->assertPresent('.label-preview')
                ->assertSee($request->request_number)
                ->assertSee($request->case_number);
        });
    }

    public function test_user_can_print_label(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/labels/evidence/request/{$request->id}/sheet")
                ->assertPresent('button:contains("Print")')
                ->click('button:contains("Print")')
                ->pause(1000);

            $this->assertDatabaseHas('label_print_logs', [
                'test_request_id' => $request->id,
                'user_id' => $user->id,
            ]);
        });
    }

    public function test_user_can_scan_barcode(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit('/labels/scan')
                ->assertSee('Scan Barcode')
                ->assertPresent('input[name="barcode"]')
                ->type('barcode', $request->request_number)
                ->keys('input[name="barcode"]', '{enter}')
                ->waitForText($request->case_number)
                ->assertSee($request->case_number);
        });
    }
}
