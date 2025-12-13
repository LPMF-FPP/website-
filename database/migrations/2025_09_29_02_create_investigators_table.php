<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rank');
            $table->string('nrp', 50)->unique();
            $table->string('jurisdiction');
            $table->string('phone');
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigators');
    }
};
