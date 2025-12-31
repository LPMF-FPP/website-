<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_request_id')->constrained('test_requests')->cascadeOnDelete();
            $table->string('name');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->unsignedInteger('order_no')->default(1);
            $table->timestamps();

            $table->index(['test_request_id', 'order_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspects');
    }
};
