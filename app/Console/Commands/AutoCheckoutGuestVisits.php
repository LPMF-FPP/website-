<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GuestVisit;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class AutoCheckoutGuestVisits extends Command
{
    protected $signature = 'guest-book:auto-checkout';

    protected $description = 'Automatically check out active guest visits older than five hours';

    public function handle(): int
    {
        $timezone = config('app.timezone');
        $now = Carbon::now($timezone);
        $cutoff = $now->copy()->subHours(5);
        $checkedOut = 0;

        GuestVisit::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(200, function ($visits) use ($cutoff, $now, &$checkedOut): void {
                $visits->each(function (GuestVisit $visit) use ($cutoff, $now, &$checkedOut): void {
                    $checkInAt = Carbon::parse(
                        $visit->visit_date->toDateString().' '.$visit->visit_time,
                        config('app.timezone')
                    );

                    if ($checkInAt->greaterThanOrEqualTo($cutoff)) {
                        return;
                    }

                    $checkedOut += GuestVisit::query()
                        ->whereKey($visit->id)
                        ->where('status', 'active')
                        ->where('visit_date', $visit->visit_date->toDateString())
                        ->where('visit_time', $visit->visit_time)
                        ->update([
                            'status' => 'checked_out',
                            'check_out_at' => $now,
                        ]);
                });
            });

        $this->info("Automatically checked out {$checkedOut} guest visit(s).");

        return self::SUCCESS;
    }
}
