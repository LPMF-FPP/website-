<?php

namespace Tests\Browser\Requests;

use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CompleteRequestLifecycleTest extends DuskTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
    }

    public function test_complete_request_creation_to_delivery_flow(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        $investigator = Investigator::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $investigator) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->assertSee('Create New Request')
                ->select('investigator_id', $investigator->id)
                ->type('request_letter_number', 'REQ-2026-001')
                ->type('request_letter_date', '2026-01-11')
                ->type('case_title', 'Test Case Investigation')
                ->type('samples[0][name]', 'Sample 1')
                ->type('samples[0][description]', 'Test sample description')
                ->type('samples[0][quantity]', '1')
                ->type('samples[0][unit]', 'kg')
                ->press('Create Request')
                ->assertPathIs('/requests')
                ->assertSee('Request created successfully')
                ->assertSee('REQ-2026-001');

            $request = TestRequest::first();
            $this->assertNotNull($request);
            $this->assertEquals('REQ-2026-001', $request->request_letter_number);

            $browser->visit("/requests/{$request->id}")
                ->assertSee($request->request_letter_number)
                ->assertSee('Test Case Investigation')
                ->assertSee($investigator->name);
        });
    }

    public function test_request_workflow_status_transitions(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        $request = TestRequest::factory()->create(['status' => 'pending']);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->assertSee($request->request_letter_number)
                ->press('Mark as In Progress')
                ->assertSee('Status updated');

            $request->refresh();
            $this->assertEquals('in_progress', $request->status);
        });
    }

    public function test_user_can_view_and_filter_requests(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        TestRequest::factory()->count(15)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests')
                ->assertSee('Requests')
                ->assertSeeIn('table', 'Request Number')
                ->assertSeeIn('table', 'Status')
                ->assertSeeIn('table', 'Date');

            $browser->type('search', 'REQ')
                ->keys('input[name="search"]', '{enter}')
                ->waitForText('REQ')
                ->assertSee('REQ');
        });
    }

    public function test_request_sample_management(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/samples/test?request_id={$request->id}")
                ->assertSee('Sample Testing')
                ->type('test_method', 'Chromatography')
                ->type('test_result', 'Positive')
                ->press('Save Test Results')
                ->assertSee('Test results saved');
        });
    }

    public function test_request_delivery_completion(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/delivery/{$request->id}")
                ->assertSee('Delivery')
                ->assertSee($request->request_letter_number)
                ->press('Mark as Completed')
                ->assertSee('Delivery completed');

            $request->refresh();
            $this->assertEquals('completed', $request->status);
        });
    }
}
