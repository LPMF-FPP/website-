<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_mutation_modes')) {
            Schema::create('inventory_mutation_modes', function (Blueprint $table): void {
                $table->unsignedSmallInteger('id')->primary();
                $table->string('mode', 32);
                $table->timestamps();
            });
        }

        DB::table('inventory_mutation_modes')->insertOrIgnore([
            'id' => 1,
            'mode' => 'disabled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the fail-closed gate state if application code is rolled back.
    }
};
