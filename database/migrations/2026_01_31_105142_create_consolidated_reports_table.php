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
        Schema::create('consolidated_reports', function (Blueprint $table) {
            $table->id();

            // Period Information
            $table->string('period_type', 20); // 'biweekly', 'monthly', 'quarterly'
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_label', 100)->nullable();

            // Report Data (JSONB)
            $table->jsonb('report_data'); // Statistics, active substances, IKU
            $table->jsonb('comparison_data')->nullable(); // Comparison with previous period
            $table->jsonb('narrative_sections'); // Opening & closing narratives
            $table->jsonb('signers'); // Array of 3 signers

            // Metadata
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->boolean('is_auto_generated')->default(false);

            // File
            $table->string('pdf_path', 500)->nullable();
            $table->integer('pdf_size')->nullable(); // Size in bytes

            // Timestamps & Soft Delete
            $table->timestamps();
            $table->softDeletes();

            // Constraints & Indexes
            $table->unique(['period_type', 'period_start', 'period_end', 'deleted_at'], 'unique_period');
            $table->index('period_type');
            $table->index('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consolidated_reports');
    }
};
