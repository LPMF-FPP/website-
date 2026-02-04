<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_whitelists', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('name')->nullable();
            $table->string('added_by')->nullable(); // Super admin who added this number
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_whitelists');
    }
};
