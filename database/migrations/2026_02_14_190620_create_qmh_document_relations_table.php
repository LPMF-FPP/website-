<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_document_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained('qmh_documents')->cascadeOnDelete();
            $table->foreignId('target_document_id')->constrained('qmh_documents')->cascadeOnDelete();
            $table->string('relation_type')->index(); // 'parent', 'child', 'reference'
            $table->timestamps();

            $table->unique(['source_document_id', 'target_document_id', 'relation_type'], 'qmh_rel_unique');
            $table->index(['target_document_id', 'relation_type']); // Reverse lookup optimization
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_document_relations');
    }
};
