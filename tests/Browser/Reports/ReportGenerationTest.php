<?php

namespace Tests\Browser\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ReportGenerationTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_user_can_view_monthly_logs_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports/monthly-logs')
                ->waitForText('Cetak Log Bulanan')
                ->assertSee('Cetak Log Bulanan');
        });
    }

    public function test_monthly_logs_page_has_environment_section(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports/monthly-logs')
                ->waitForText('Cetak Log Bulanan')
                ->assertSee('Suhu & Kelembaban');
        });
    }

    public function test_monthly_logs_page_has_instrument_section(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports/monthly-logs')
                ->waitForText('Cetak Log Bulanan')
                ->assertSee('Penggunaan Instrumen');
        });
    }

    public function test_monthly_logs_has_pdf_and_csv_buttons(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports/monthly-logs')
                ->waitForText('Cetak Log Bulanan')
                ->assertSee('PDF')
                ->assertSee('Excel (CSV)');
        });
    }
}
