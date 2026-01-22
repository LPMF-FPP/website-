<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('method_instrument_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('method_code');
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->boolean('mandatory')->default(true);
            $table->enum('usage_type', ['PREP', 'RUN'])->default('RUN');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamps();

            $table->unique(['method_code', 'instrument_id', 'usage_type'], 'method_instrument_usage_unique');
            $table->index('method_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('method_instrument_requirements');
    }
};
