<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel permissions - daftar semua permission yang tersedia
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();        // e.g. "permintaan.view"
            $table->string('display_name');          // e.g. "Lihat Permintaan"
            $table->string('module');                // e.g. "permintaan"
            $table->string('action');                // e.g. "view", "create", "edit", "delete"
            $table->timestamps();

            $table->index('module');
            $table->index('action');
        });

        // Tabel role_permissions - default permission untuk setiap role
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');                  // e.g. "analis", "admin"
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['role', 'permission_id'], 'unique_role_permission');
            $table->index('role');
        });

        // Tabel user_permissions - custom override permission per user
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->boolean('granted')->default(true);  // true = granted, false = revoked
            $table->timestamps();

            $table->unique(['user_id', 'permission_id'], 'unique_user_permission');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
