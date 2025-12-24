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
        Schema::table('document_templates', function (Blueprint $table) {
            // Add new columns for versioning and format support
            $table->string('type')->after('code')->nullable()->index();
            $table->string('format')->after('type')->default('pdf');
            $table->boolean('is_active')->after('format')->default(false)->index();
            $table->unsignedInteger('version')->after('is_active')->default(1);
            $table->string('checksum')->after('version')->nullable();
            $table->foreignId('created_by')->after('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Add composite unique index for (type, format, version)
            $table->unique(['type', 'format', 'version'], 'templates_type_format_version_unique');
            // Add index for finding active templates
            $table->index(['type', 'format', 'is_active'], 'templates_active_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropIndex('templates_active_lookup');
            $table->dropUnique('templates_type_format_version_unique');
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'type',
                'format',
                'is_active',
                'version',
                'checksum',
                'created_by',
            ]);
        });
    }
};
