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
        Schema::table('documents', function (Blueprint $table) {
            // Add investigator_id if not exists
            if (!Schema::hasColumn('documents', 'investigator_id')) {
                $table->foreignId('investigator_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            }
            
            // Add source column (upload or generated)
            if (!Schema::hasColumn('documents', 'source')) {
                $table->enum('source', ['upload', 'generated'])->default('upload')->after('document_type');
            }
            
            // Add filename column (original filename)
            if (!Schema::hasColumn('documents', 'filename')) {
                $table->string('filename')->nullable()->after('file_path');
            }
            
            // Add path column (storage path relative to disk)
            if (!Schema::hasColumn('documents', 'path')) {
                $table->string('path')->nullable()->after('filename');
            }
            
            // Add extra column for additional metadata (JSON)
            if (!Schema::hasColumn('documents', 'extra')) {
                $table->json('extra')->nullable()->after('mime_type');
            }
            
            // Add file_size column
            if (!Schema::hasColumn('documents', 'file_size')) {
                $table->bigInteger('file_size')->nullable()->after('mime_type');
            }
            
            // Add indexes
            $table->index('investigator_id');
            $table->index(['investigator_id', 'test_request_id']);
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['investigator_id']);
            $table->dropIndex(['investigator_id', 'test_request_id']);
            $table->dropIndex(['source']);
            
            if (Schema::hasColumn('documents', 'investigator_id')) {
                $table->dropForeign(['investigator_id']);
                $table->dropColumn('investigator_id');
            }
            if (Schema::hasColumn('documents', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('documents', 'filename')) {
                $table->dropColumn('filename');
            }
            if (Schema::hasColumn('documents', 'path')) {
                $table->dropColumn('path');
            }
            if (Schema::hasColumn('documents', 'extra')) {
                $table->dropColumn('extra');
            }
            if (Schema::hasColumn('documents', 'file_size')) {
                $table->dropColumn('file_size');
            }
        });
    }
};
