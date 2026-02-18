<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qmh_templates')) {
            return;
        }

        $cleanupEnabled = (bool) config('quality.fr_v2.docx_cleanup_enabled', false);
        if (! $cleanupEnabled) {
            return;
        }

        if (! Schema::hasColumn('qmh_templates', 'source_docx_path')) {
            return;
        }

        $stillUsed = DB::table('qmh_templates')
            ->whereNotNull('source_docx_path')
            ->where('source_docx_path', '!=', '')
            ->exists();

        if ($stillUsed) {
            throw new RuntimeException('Kolom source_docx_path masih terisi. Bersihkan data legacy sebelum cleanup DOCX.');
        }

        Schema::table('qmh_templates', function (Blueprint $table): void {
            $table->dropColumn('source_docx_path');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qmh_templates')) {
            return;
        }

        if (Schema::hasColumn('qmh_templates', 'source_docx_path')) {
            return;
        }

        Schema::table('qmh_templates', function (Blueprint $table): void {
            $table->string('source_docx_path')->nullable()->after('storage_disk');
        });
    }
};
