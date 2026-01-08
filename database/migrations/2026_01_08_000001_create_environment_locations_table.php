<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environment_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['room', 'fridge', 'freezer', 'other'])->default('room');
            
            $table->decimal('target_temp_min', 5, 2)->nullable();
            $table->decimal('target_temp_max', 5, 2)->nullable();
            $table->decimal('target_hum_min', 5, 2)->nullable();
            $table->decimal('target_hum_max', 5, 2)->nullable();
            
            $table->json('schedule_windows')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environment_locations');
    }
};
