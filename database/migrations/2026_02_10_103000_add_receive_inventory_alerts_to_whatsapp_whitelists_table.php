<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_whitelists', function (Blueprint $table) {
            $table
                ->boolean('receive_inventory_alerts')
                ->default(true)
                ->after('added_by');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_whitelists', function (Blueprint $table) {
            $table->dropColumn('receive_inventory_alerts');
        });
    }
};
