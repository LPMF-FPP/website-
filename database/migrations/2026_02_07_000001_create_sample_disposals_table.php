<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_disposals', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->timestamp('executed_at');
            $table->enum('method', ['bakar', 'kubur', 'hancur', 'lainnya']);
            $table->string('witness_name');
            $table->string('witness_role');
            $table->text('notes')->nullable();
            $table->foreignId('executed_by')->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('executed_at');
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_disposals');
    }
};
