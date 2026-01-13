<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Sensors configuration
        Schema::create('monitoring_sensors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            $table->string('type')->default('TEMPERATURE'); // TEMPERATURE, HUMIDITY, BOTH
            $table->decimal('min_threshold', 8, 2)->nullable();
            $table->decimal('max_threshold', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_reading_at')->nullable();
            $table->decimal('last_value', 8, 2)->nullable();
            $table->timestamps();
        });

        // Sensor logs (high volume)
        Schema::create('monitoring_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('monitoring_sensors')->cascadeOnDelete();
            $table->decimal('value', 8, 2);
            $table->decimal('secondary_value', 8, 2)->nullable(); // e.g. humidity if main is temp
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['sensor_id', 'recorded_at']);
        });

        // Alerts
        Schema::create('monitoring_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('monitoring_sensors')->cascadeOnDelete();
            $table->string('type'); // LOW_TEMP, HIGH_TEMP, etc.
            $table->decimal('value', 8, 2);
            $table->decimal('threshold', 8, 2);
            $table->enum('status', ['OPEN', 'ACKNOWLEDGED', 'RESOLVED'])->default('OPEN');
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_alerts');
        Schema::dropIfExists('monitoring_logs');
        Schema::dropIfExists('monitoring_sensors');
        Schema::dropIfExists('monitoring_tables'); // Clean up the mistake if it was created
    }
};
