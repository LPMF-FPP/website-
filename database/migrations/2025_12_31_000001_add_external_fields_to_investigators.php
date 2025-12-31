<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investigators', function (Blueprint $table) {
            $table->boolean('is_polri')->default(true)->after('id');
            $table->string('institution')->nullable()->after('jurisdiction');
            $table->string('occupation')->nullable()->after('institution');
            $table->string('alt_phone')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('investigators', function (Blueprint $table) {
            $table->dropColumn(['is_polri', 'institution', 'occupation', 'alt_phone']);
        });
    }
};
