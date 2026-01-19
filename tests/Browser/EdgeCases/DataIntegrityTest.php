<?php

namespace Tests\Browser\EdgeCases;

use App\Models\Investigator;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DataIntegrityTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_database_constraint_violation_handled_gracefully(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->type('request_number', str_repeat('A', 300))
                ->press('Create Request')
                ->waitForText('too long')
                ->assertSee('error');
        });
    }

    public function test_transaction_rollback_on_error(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $investigator = Investigator::factory()->create();

        $initialCount = TestRequest::count();

        $this->browse(function (Browser $browser) use ($user, $investigator) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->select('investigator_id', $investigator->id)
                ->type('request_number', '')
                ->press('Create Request')
                ->waitForText('required');
        });

        $this->assertEquals($initialCount, TestRequest::count());
    }

    public function test_audit_trail_records_all_changes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $request = TestRequest::factory()->create(['status' => 'pending']);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}/edit")
                ->select('status', 'in_progress')
                ->press('Save')
                ->waitForText('updated');
        });

        $this->assertTrue(
            DB::table('activity_logs')
                ->where('subject_type', TestRequest::class)
                ->where('subject_id', $request->id)
                ->where('causer_id', $user->id)
                ->exists()
        );
    }
}
