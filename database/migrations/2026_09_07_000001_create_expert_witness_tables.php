<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_witness_requests', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->foreignId('test_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('investigator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('letter_number');
            $table->date('letter_date');
            $table->string('investigator_name');
            $table->string('investigator_institution');
            $table->string('investigator_phone', 30);
            $table->string('sprin_number')->nullable();
            $table->date('sprin_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'submitted_at']);
        });

        Schema::create('expert_witness_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_witness_request_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['expert_witness_request_id', 'code']);
        });

        Schema::create('expert_witness_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expert_witness_request_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('disk', 50);
            $table->string('path');
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_witness_documents');
        Schema::dropIfExists('expert_witness_milestones');
        Schema::dropIfExists('expert_witness_requests');
    }
};
