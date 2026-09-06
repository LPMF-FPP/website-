<?php

namespace Tests\Feature;

use App\Models\GuestVisit;
use App\Models\Investigator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCheckoutGuestVisitsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_checks_out_active_visits_older_than_five_hours(): void
    {
        $now = Carbon::create(2026, 9, 4, 15, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $visit = $this->createVisit($now->copy()->subHours(5)->subMinute());

        $this->artisan('guest-book:auto-checkout')
            ->assertSuccessful();

        $visit->refresh();

        $this->assertSame('checked_out', $visit->status);
        $this->assertSame($now->toJSON(), $visit->check_out_at->toJSON());
    }

    public function test_it_leaves_visits_at_or_under_the_five_hour_limit_active(): void
    {
        $now = Carbon::create(2026, 9, 4, 15, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $exactLimit = $this->createVisit($now->copy()->subHours(5));
        $underLimit = $this->createVisit($now->copy()->subHours(4)->subMinute());

        $this->artisan('guest-book:auto-checkout')
            ->assertSuccessful();

        $this->assertSame('active', $exactLimit->refresh()->status);
        $this->assertNull($exactLimit->check_out_at);
        $this->assertSame('active', $underLimit->refresh()->status);
        $this->assertNull($underLimit->check_out_at);
    }

    public function test_it_does_not_change_an_existing_checkout(): void
    {
        $now = Carbon::create(2026, 9, 4, 15, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        $checkedOutAt = $now->copy()->subHour();

        $visit = $this->createVisit($now->copy()->subHours(8));
        $visit->forceFill([
            'status' => 'checked_out',
            'check_out_at' => $checkedOutAt,
        ])->save();

        $this->artisan('guest-book:auto-checkout')
            ->assertSuccessful();

        $this->assertSame('checked_out', $visit->refresh()->status);
        $this->assertSame($checkedOutAt->timestamp, $visit->check_out_at->timestamp);
    }

    public function test_it_leaves_other_non_active_statuses_unchanged(): void
    {
        $now = Carbon::create(2026, 9, 4, 15, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        $visit = $this->createVisit($now->copy()->subHours(8));
        $visit->forceFill(['status' => 'cancelled'])->save();

        $this->artisan('guest-book:auto-checkout')
            ->assertSuccessful();

        $this->assertSame('cancelled', $visit->refresh()->status);
        $this->assertNull($visit->check_out_at);
    }

    public function test_it_is_idempotent_when_run_again(): void
    {
        $now = Carbon::create(2026, 9, 4, 15, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        $visit = $this->createVisit($now->copy()->subHours(6));

        $this->artisan('guest-book:auto-checkout')->assertSuccessful();
        $firstCheckoutAt = $visit->refresh()->check_out_at->toJSON();

        Carbon::setTestNow($now->copy()->addMinute());
        $this->artisan('guest-book:auto-checkout')->assertSuccessful();

        $this->assertSame('checked_out', $visit->refresh()->status);
        $this->assertSame($firstCheckoutAt, $visit->check_out_at->toJSON());
    }

    public function test_it_uses_the_combined_date_and_time_across_midnight(): void
    {
        $now = Carbon::create(2026, 9, 4, 1, 1, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        $visit = $this->createVisit(Carbon::create(2026, 9, 3, 20, 0, 0, 'Asia/Jakarta'));

        $this->artisan('guest-book:auto-checkout')
            ->assertSuccessful();

        $this->assertSame('checked_out', $visit->refresh()->status);
    }

    private function createVisit(Carbon $checkInAt, array $overrides = []): GuestVisit
    {
        return GuestVisit::query()->create(array_merge([
            'investigator_id' => Investigator::factory()->create()->id,
            'visit_date' => $checkInAt->toDateString(),
            'visit_time' => $checkInAt->format('H:i:s'),
            'purpose' => 'Pelatihan',
            'visitor_name' => 'Tamu Pengujian',
        ], $overrides));
    }
}
