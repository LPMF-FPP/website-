<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Convert existing data to valid JSON strings first
        $reminders = DB::table('reminders')->get();

        foreach ($reminders as $reminder) {
            $oldValue = $reminder->schedule_days;
            // Handle potential existing valid JSON or convert keywords
            if (str_starts_with($oldValue, '[')) {
                $newValue = $oldValue;
            } else {
                $newValue = match ($oldValue) {
                    'daily' => '["Mon","Tue","Wed","Thu","Fri","Sat","Sun"]',
                    'weekdays' => '["Mon","Tue","Wed","Thu","Fri"]',
                    default => '["Mon","Tue","Wed","Thu","Fri"]',
                };
            }

            DB::table('reminders')
                ->where('id', $reminder->id)
                ->update(['schedule_days' => $newValue]);
        }

        // 1.5 Drop old default first to avoid casting error
        DB::statement('ALTER TABLE reminders ALTER COLUMN schedule_days DROP DEFAULT');

        // 2. Change column type to jsonb
        // Using raw SQL for PostgreSQL specific casting
        DB::statement('ALTER TABLE reminders ALTER COLUMN schedule_days TYPE jsonb USING schedule_days::jsonb');

        // 3. Set new default
        DB::statement("ALTER TABLE reminders ALTER COLUMN schedule_days SET DEFAULT '[\"Mon\",\"Tue\",\"Wed\",\"Thu\",\"Fri\"]'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to varchar, losing detailed day selection (fallback to daily)
        DB::statement("ALTER TABLE reminders ALTER COLUMN schedule_days TYPE varchar(50) USING 'daily'");
        DB::statement("ALTER TABLE reminders ALTER COLUMN schedule_days SET DEFAULT 'daily'");
    }
};
