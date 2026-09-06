<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GuestVisit;
use App\Models\Investigator;
use App\Models\TestRequest;
use App\Services\GuestVisitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestVisitGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_submission_requests_for_one_owner_share_one_visit_group(): void
    {
        $now = Carbon::create(2026, 9, 6, 9, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        $investigator = Investigator::factory()->create();
        $requests = TestRequest::factory()->count(3)->create(['investigator_id' => $investigator->id]);

        foreach ($requests as $request) {
            app(GuestVisitService::class)->recordRequest($request, 'Permohonan Pengujian', null);
        }

        $visit = GuestVisit::query()->where('purpose', 'Permohonan Pengujian')->firstOrFail();
        $this->assertSame(1, GuestVisit::query()->where('purpose', 'Permohonan Pengujian')->count());
        $this->assertCount(3, $visit->items);
        $this->assertSame($investigator->id, $visit->investigator_id);
        $this->assertSame(3, $visit->request_count);
        $this->assertNull($visit->visitor_name);
    }

    public function test_collection_requests_for_one_owner_share_one_visit_group(): void
    {
        $now = Carbon::create(2026, 9, 6, 10, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        $investigator = Investigator::factory()->create();
        $requests = TestRequest::factory()->count(3)->create(['investigator_id' => $investigator->id]);

        foreach ($requests as $request) {
            app(GuestVisitService::class)->recordRequest($request, 'Pengambilan Hasil Pengujian', null);
        }

        $visit = GuestVisit::query()->where('purpose', 'Pengambilan Hasil Pengujian')->firstOrFail();
        $this->assertSame(1, GuestVisit::query()->where('purpose', 'Pengambilan Hasil Pengujian')->count());
        $this->assertCount(3, $visit->items);
    }

    public function test_submission_and_collection_are_separate_groups(): void
    {
        $now = Carbon::create(2026, 9, 6, 11, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($now);
        $investigator = Investigator::factory()->create();
        $request = TestRequest::factory()->create(['investigator_id' => $investigator->id]);
        $service = app(GuestVisitService::class);

        $service->recordRequest($request, 'Permohonan Pengujian', null);
        $service->recordRequest($request, 'Pengambilan Hasil Pengujian', null);

        $this->assertSame(2, GuestVisit::query()->count());
        $this->assertDatabaseHas('guest_visit_items', [
            'test_request_id' => $request->id,
            'activity_type' => 'submission',
        ]);
        $this->assertDatabaseHas('guest_visit_items', [
            'test_request_id' => $request->id,
            'activity_type' => 'collection',
        ]);
    }

    public function test_different_owners_have_separate_groups(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 6, 12, 0, 0, 'Asia/Jakarta'));
        $investigators = Investigator::factory()->count(2)->create();
        $service = app(GuestVisitService::class);

        foreach ($investigators as $investigator) {
            $service->recordRequest(
                TestRequest::factory()->create(['investigator_id' => $investigator->id]),
                'Pengambilan Hasil Pengujian',
                null
            );
        }

        $this->assertSame(2, GuestVisit::query()->count());
        $this->assertSame(2, GuestVisit::query()->distinct('investigator_id')->count('investigator_id'));
    }

    public function test_request_after_five_hours_starts_a_new_group(): void
    {
        $investigator = Investigator::factory()->create();
        $service = app(GuestVisitService::class);
        Carbon::setTestNow(Carbon::create(2026, 9, 6, 9, 0, 0, 'Asia/Jakarta'));
        $first = TestRequest::factory()->create(['investigator_id' => $investigator->id]);
        $service->recordRequest($first, 'Pengambilan Hasil Pengujian', null);

        Carbon::setTestNow(Carbon::create(2026, 9, 6, 14, 1, 0, 'Asia/Jakarta'));
        $second = TestRequest::factory()->create(['investigator_id' => $investigator->id]);
        $service->recordRequest($second, 'Pengambilan Hasil Pengujian', null);

        $this->assertSame(2, GuestVisit::query()->count());
        $this->assertCount(1, GuestVisit::query()->firstOrFail()->items);
        $this->assertCount(1, GuestVisit::query()->latest('id')->firstOrFail()->items);
    }

    public function test_repeating_the_same_request_does_not_duplicate_the_item(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 6, 13, 0, 0, 'Asia/Jakarta'));
        $request = TestRequest::factory()->create();
        $service = app(GuestVisitService::class);

        $service->recordRequest($request, 'Pengambilan Hasil Pengujian', null);
        $service->recordRequest($request, 'Pengambilan Hasil Pengujian', null);

        $this->assertSame(1, GuestVisit::query()->count());
        $this->assertDatabaseCount('guest_visit_items', 1);
    }
}
