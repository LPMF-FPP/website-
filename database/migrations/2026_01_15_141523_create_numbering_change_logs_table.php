<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_change_logs', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 50)->index(); // ba, lhu, sample_code, ba_penyerahan, tracking
            $table->string('action_type', 30); // reset, sync_max, sync_count, edit
            $table->string('entity_type', 100)->nullable(); // App\Models\TestRequest, etc
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('old_value', 255);
            $table->string('new_value', 255);
            $table->text('reason');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_change_logs');
    }
};
