<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['investigator', 'analyst', 'admin', 'supervisor'])->default('investigator');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        // Use raw SQL to force cascade drop on Postgres
        // This prevents "cannot drop table users because other objects depend on it"
        DB::statement('DROP TABLE IF EXISTS users CASCADE');
    }
};
