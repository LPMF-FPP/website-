<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->decimal('uvvis_weighed_grams', 12, 4)->nullable()->after('active_substance');
            $table->foreignId('uvvis_weighed_by')->nullable()->after('uvvis_weighed_grams')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('uvvis_weighed_at')->nullable()->after('uvvis_weighed_by');
        });
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uvvis_weighed_by');
            $table->dropColumn(['uvvis_weighed_grams', 'uvvis_weighed_at']);
        });
    }
};
