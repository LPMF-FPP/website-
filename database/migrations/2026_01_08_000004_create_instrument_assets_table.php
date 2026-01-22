<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('asset_code')->unique();
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['active', 'maintenance', 'out_of_service', 'calibration_due'])->default('active');
            $table->date('last_calibration_at')->nullable();
            $table->date('calibration_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('calibration_due_at');
            $table->index(['instrument_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_assets');
    }
};
