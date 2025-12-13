<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('investigators', function (Blueprint $table) {
            $table->string('folder_key')->nullable()->unique()->after('nrp');
            $table->index('folder_key');
        });

        // Generate folder_key for existing investigators
        DB::table('investigators')->orderBy('id')->each(function ($investigator) {
            $slug = Str::slug($investigator->name);
            $folderKey = $investigator->nrp ? "{$investigator->nrp}-{$slug}" : $slug;
            
            // Ensure uniqueness
            $original = $folderKey;
            $counter = 1;
            while (DB::table('investigators')->where('folder_key', $folderKey)->exists()) {
                $folderKey = "{$original}-{$counter}";
                $counter++;
            }
            
            DB::table('investigators')
                ->where('id', $investigator->id)
                ->update(['folder_key' => $folderKey]);
        });

        // Make folder_key not nullable after populating
        Schema::table('investigators', function (Blueprint $table) {
            $table->string('folder_key')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investigators', function (Blueprint $table) {
            $table->dropIndex(['folder_key']);
            $table->dropColumn('folder_key');
        });
    }
};
