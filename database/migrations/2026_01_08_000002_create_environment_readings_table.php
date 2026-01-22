<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_readings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('location_id')->constrained('environment_locations')->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->decimal('temperature_c', 5, 2)->nullable();
            $table->decimal('humidity_rh', 5, 2)->nullable();
            $table->foreignId('entered_by')->constrained('users')->cascadeOnDelete();
            $table->enum('source', ['manual', 'import', 'iot'])->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('correction_of_id')->nullable()->constrained('environment_readings')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('measured_at');
            $table->index(['location_id', 'measured_at']);
            $table->index('correction_of_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_readings');
    }
};
