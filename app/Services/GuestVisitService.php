<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GuestVisit;
use App\Models\GuestVisitItem;
use App\Models\Investigator;
use App\Models\TestRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class GuestVisitService
{
    private const SESSION_HOURS = 5;

    public function recordRequest(TestRequest $request, string $purpose, ?int $userId): GuestVisit
    {
        $now = now();
        $visit = DB::transaction(function () use ($request, $purpose, $userId, $now): GuestVisit {
            if ($request->investigator_id) {
                Investigator::query()
                    ->whereKey($request->investigator_id)
                    ->lockForUpdate()
                    ->first();
            }

            $visit = $this->findActiveVisit($request->investigator_id, $purpose, $now);

            if (! $visit) {
                $investigator = $request->investigator;
                $visit = GuestVisit::create([
                    'investigator_id' => $request->investigator_id,
                    'test_request_id' => $request->id,
                    'visit_date' => $now->toDateString(),
                    'visit_time' => $now->toTimeString(),
                    'purpose' => $purpose,
                    'host_id' => $userId,
                    'visitor_name' => null,
                    'visitor_identity' => null,
                    'visitor_relation' => null,
                    'visitor_phone' => null,
                    'created_by' => $userId,
                ]);

                $visit->forceFill([
                    'nda_accepted' => true,
                    'nda_accepted_at' => $now,
                ])->save();
            }

            $this->attachRequest($visit, $request);

            return $visit;
        });

        return $visit;
    }

    private function findActiveVisit(?int $investigatorId, string $purpose, Carbon $now): ?GuestVisit
    {
        if (! $investigatorId) {
            return null;
        }

        $cutoff = $now->copy()->subHours(self::SESSION_HOURS);

        return GuestVisit::query()
            ->where('status', 'active')
            ->where('investigator_id', $investigatorId)
            ->where('purpose', $purpose)
            ->whereDate('visit_date', '>=', $cutoff->toDateString())
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->lockForUpdate()
            ->get()
            ->first(function (GuestVisit $visit) use ($cutoff): bool {
                $checkInAt = Carbon::parse(
                    $visit->visit_date->toDateString().' '.$visit->visit_time,
                    config('app.timezone')
                );

                return $checkInAt->greaterThanOrEqualTo($cutoff);
            });
    }

    private function attachRequest(GuestVisit $visit, TestRequest $request): void
    {
        GuestVisitItem::query()->firstOrCreate([
            'guest_visit_id' => $visit->id,
            'test_request_id' => $request->id,
            'activity_type' => $this->activityType($visit->purpose),
        ], [
            'investigator_id' => $request->investigator_id,
        ]);
    }

    private function activityType(string $purpose): string
    {
        return $purpose === 'Pengambilan Hasil Pengujian' ? 'collection' : 'submission';
    }
}
