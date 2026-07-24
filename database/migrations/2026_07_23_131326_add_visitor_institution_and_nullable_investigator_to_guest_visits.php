<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_visits', function (Blueprint $table) {
            $table->foreignId('investigator_id')->nullable()->change();
            $table->string('visitor_institution')->nullable()->after('visitor_identity');
        });
    }

    public function down(): void
    {
        Schema::table('guest_visits', function (Blueprint $table) {
            $table->foreignId('investigator_id')->nullable(false)->change();
            $table->dropColumn('visitor_institution');
        });
    }
};
