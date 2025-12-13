<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained();
            $table->foreignId('tested_by')->constrained('users'); // Lab analyst

            // Test method and equipment
            $table->string('test_method');
            $table->string('equipment_used');
            $table->text('test_conditions')->nullable();

            // Results
            $table->json('active_substances'); // JSON array of substances found
            $table->decimal('purity_percentage', 5, 2)->nullable();
            $table->text('test_conclusion');
            $table->enum('result_status', ['positive', 'negative', 'inconclusive']);

            // Supporting data
            $table->string('chromatogram_path')->nullable();
            $table->string('spectrum_path')->nullable();
            $table->text('analyst_notes')->nullable();

            // Quality control
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('qc_approved')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
