<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->text('requested_test_methods')->nullable()->after('test_methods');
        });

        DB::table('samples')
            ->whereNull('requested_test_methods')
            ->update([
                'requested_test_methods' => DB::raw('test_methods'),
            ]);
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropColumn('requested_test_methods');
        });
    }
};
