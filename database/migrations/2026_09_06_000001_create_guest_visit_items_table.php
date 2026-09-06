<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_visit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_visit_id')->constrained('guest_visits')->cascadeOnDelete();
            $table->foreignId('test_request_id')->constrained('test_requests')->cascadeOnDelete();
            $table->foreignId('investigator_id')->nullable()->constrained('investigators')->nullOnDelete();
            $table->string('activity_type');
            $table->timestamps();

            $table->unique(['guest_visit_id', 'test_request_id', 'activity_type']);
            $table->index(['investigator_id', 'activity_type']);
        });

        DB::table('guest_visits')
            ->whereNotNull('test_request_id')
            ->orderBy('id')
            ->eachById(function (object $visit): void {
                DB::table('guest_visit_items')->insertOrIgnore([
                    'guest_visit_id' => $visit->id,
                    'test_request_id' => $visit->test_request_id,
                    'investigator_id' => $visit->investigator_id,
                    'activity_type' => $visit->purpose === 'Pengambilan Hasil Pengujian'
                        ? 'collection'
                        : 'submission',
                    'created_at' => $visit->created_at,
                    'updated_at' => $visit->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_visit_items');
    }
};
