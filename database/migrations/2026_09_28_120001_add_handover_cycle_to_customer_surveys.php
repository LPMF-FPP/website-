<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_surveys', function (Blueprint $table): void {
            $table->dropUnique('customer_surveys_test_request_id_unique');
            $table->unsignedSmallInteger('handover_cycle')->default(1)->after('test_request_id');
            $table->unique(['test_request_id', 'handover_cycle'], 'customer_surveys_request_cycle_unique');
        });
    }

    public function down(): void
    {
        $hasMultipleCycles = DB::table('customer_surveys')
            ->select('test_request_id')
            ->groupBy('test_request_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleCycles) {
            throw new RuntimeException('Cannot restore one-survey-per-request constraint while multiple handover-cycle surveys exist.');
        }

        Schema::table('customer_surveys', function (Blueprint $table): void {
            $table->dropUnique('customer_surveys_request_cycle_unique');
            $table->dropColumn('handover_cycle');
            $table->unique('test_request_id');
        });
    }
};
