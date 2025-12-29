<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remaining_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_unit_id')->constrained('evidence_units')->onDelete('cascade');
            $table->string('sample_code');           // Denormalized for fast lookup/print
            $table->string('remaining_code')->unique(); // "{sample_code}-SISA" or "{sample_code}-SISA-n"
            $table->decimal('qty_remaining', 10, 2)->nullable();
            $table->string('uom')->nullable();
            $table->string('seal_status_delivered')->nullable();
            $table->string('condition_delivered')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('handover_doc_no')->nullable();
            $table->string('qr_token', 16)->unique();
            $table->timestamps();

            $table->index('evidence_unit_id');
            $table->index('sample_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remaining_units');
    }
};
