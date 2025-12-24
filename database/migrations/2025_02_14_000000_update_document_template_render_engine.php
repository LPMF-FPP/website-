<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->where('render_engine', 'browsershot')
            ->update(['render_engine' => 'dompdf']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('document_templates')) {
            return;
        }

        DB::table('document_templates')
            ->where('render_engine', 'dompdf')
            ->update(['render_engine' => 'browsershot']);
    }
};
