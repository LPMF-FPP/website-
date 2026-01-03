<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('samples', 'short_description')) {
            Schema::table('samples', function (Blueprint $table) {
                $table->string('short_description')->nullable()->after('sample_code');
            });
        }

        if (! Schema::hasColumn('samples', 'unit')) {
            Schema::table('samples', function (Blueprint $table) {
                $table->string('unit', 50)->nullable()->after('package_quantity');
            });
        }

        if (Schema::hasColumn('samples', 'sample_name')) {
            DB::table('samples')
                ->whereNull('short_description')
                ->update(['short_description' => DB::raw('sample_name')]);
        }

        if (Schema::hasColumn('samples', 'packaging_type')) {
            DB::table('samples')
                ->whereNull('unit')
                ->whereNotNull('packaging_type')
                ->update(['unit' => DB::raw('packaging_type')]);
        }

        if (Schema::hasColumn('samples', 'sample_name')) {
            Schema::table('samples', function (Blueprint $table) {
                $table->dropColumn('sample_name');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('samples', 'sample_name')) {
            Schema::table('samples', function (Blueprint $table) {
                $table->string('sample_name')->nullable()->after('sample_code');
            });
        }

        if (Schema::hasColumn('samples', 'short_description')) {
            DB::table('samples')
                ->whereNull('sample_name')
                ->update(['sample_name' => DB::raw('short_description')]);
        }

        Schema::table('samples', function (Blueprint $table) {
            if (Schema::hasColumn('samples', 'short_description')) {
                $table->dropColumn('short_description');
            }
            if (Schema::hasColumn('samples', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};
