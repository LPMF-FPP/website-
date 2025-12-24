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
        Schema::table('users', function (Blueprint $table) {
            $table->string('title_prefix')->nullable();
            $table->string('title_suffix')->nullable();
            $table->string('rank')->nullable();
            $table->string('nrp', 50)->nullable();
            $table->string('nip', 50)->nullable();
            $table->foreignId('investigator_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['investigator_id']);
            $table->dropColumn(['title_prefix', 'title_suffix', 'rank', 'nrp', 'nip', 'investigator_id']);
        });
    }
};
