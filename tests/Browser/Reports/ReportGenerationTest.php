<?php

namespace Tests\Browser\Reports;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ReportGenerationTest extends DuskTestCase
{
    use DatabaseTransactions;

    public function test_user_can_view_reports_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports')
                ->assertSee('Reports')
                ->assertPresent('.report-list');
        });
    }

    public function test_user_can_generate_monthly_report(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        TestRequest::factory()->count(10)->create([
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports')
                ->assertPresent('select[name="month"]')
                ->assertPresent('select[name="year"]')
                ->select('month', now()->month)
                ->select('year', now()->year)
                ->press('Generate Report')
                ->waitForText('Report Generated')
                ->assertSee('Report Generated')
                ->assertPresent('.report-content');
        });
    }

    public function test_user_can_generate_custom_date_range_report(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $startDate = now()->subDays(30)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        TestRequest::factory()->count(15)->create();

        $this->browse(function (Browser $browser) use ($user, $startDate, $endDate) {
            $browser->loginAs($user)
                ->visit('/reports')
                ->click('a:contains("Custom Range")')
                ->waitFor('#custom-range-form')
                ->type('start_date', $startDate)
                ->type('end_date', $endDate)
                ->press('Generate')
                ->waitForText('Report Generated')
                ->assertSee('Report Generated')
                ->assertSee($startDate)
                ->assertSee($endDate);
        });
    }

    public function test_user_can_export_report_to_pdf(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        TestRequest::factory()->count(5)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports')
                ->select('month', now()->month)
                ->select('year', now()->year)
                ->press('Generate Report')
                ->waitForText('Report Generated')
                ->assertPresent('button:contains("Export PDF")')
                ->click('button:contains("Export PDF")')
                ->pause(1000);
        });
    }

    public function test_user_can_export_report_to_excel(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        TestRequest::factory()->count(5)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/reports')
                ->select('month', now()->month)
                ->select('year', now()->year)
                ->press('Generate Report')
                ->waitForText('Report Generated')
                ->assertPresent('button:contains("Export Excel")')
                ->click('button:contains("Export Excel")')
                ->pause(1000);
        });
    }
}
