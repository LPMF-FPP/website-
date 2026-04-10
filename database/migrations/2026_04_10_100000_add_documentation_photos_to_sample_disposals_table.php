<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->json('documentation_photos')
                ->nullable()
                ->after('approver_identity');
        });
    }

    public function down(): void
    {
        Schema::table('sample_disposals', function (Blueprint $table) {
            $table->dropColumn('documentation_photos');
        });
    }
};
