<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'doc_type')) {
                $table->string('doc_type')->nullable()->after('document_type');
            }
            if (!Schema::hasColumn('documents', 'ba_no')) {
                $table->string('ba_no')->nullable()->after('doc_type');
            }
            if (!Schema::hasColumn('documents', 'title')) {
                $table->string('title')->nullable()->after('ba_no');
            }
            if (!Schema::hasColumn('documents', 'lp_no')) {
                $table->string('lp_no')->nullable()->after('title');
            }
            if (!Schema::hasColumn('documents', 'doc_date')) {
                $table->date('doc_date')->nullable()->after('lp_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            foreach (['doc_type', 'ba_no', 'title', 'lp_no', 'doc_date'] as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
