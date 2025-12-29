<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('test_requests')->onDelete('cascade');
            $table->foreignId('sample_id')->constrained('samples')->onDelete('cascade');
            $table->string('receipt_code')->nullable();        // Resi/tracking number
            $table->string('sample_code');                     // Copy from samples.sample_code
            $table->string('sample_type')->nullable();
            $table->text('sample_desc')->nullable();
            $table->string('investigator_name')->nullable();
            $table->string('investigator_unit')->nullable();
            $table->string('seal_status_received')->nullable();
            $table->string('condition_received')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('qr_token', 16)->unique();
            $table->timestamps();

            $table->unique('sample_id');  // One evidence unit per sample
            $table->index('request_id');
            $table->index('sample_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_units');
    }
};
