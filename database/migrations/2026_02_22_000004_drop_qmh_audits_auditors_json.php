<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('qmh_audits', 'auditors_json')) {
            return;
        }

        Schema::table('qmh_audits', function (Blueprint $table) {
            $table->dropColumn('auditors_json');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('qmh_audits', 'auditors_json')) {
            return;
        }

        Schema::table('qmh_audits', function (Blueprint $table) {
            $table->json('auditors_json')->nullable()->after('location');
        });
    }
};
