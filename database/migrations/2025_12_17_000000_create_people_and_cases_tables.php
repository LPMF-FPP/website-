<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('people')) {
            Schema::create('people', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->text('name');
                $table->text('role')->nullable();
                $table->text('photo_path')->nullable();
                $table->timestampTz('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('cases')) {
            Schema::create('cases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->text('title');
                $table->text('lp_no');
                $table->timestampTz('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('case_people')) {
            Schema::create('case_people', function (Blueprint $table) {
                $table->unsignedBigInteger('case_id');
                $table->unsignedBigInteger('person_id');
                $table->text('role_in_case')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_people');
        Schema::dropIfExists('cases');
        Schema::dropIfExists('people');
    }
};
