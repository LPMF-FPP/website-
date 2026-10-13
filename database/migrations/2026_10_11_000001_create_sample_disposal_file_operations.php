<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_disposal_file_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_key', 64)->unique();
            $table->string('kind', 20);
            $table->string('disk', 20);
            $table->text('path');
            $table->text('destination_path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('state', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 100)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_disposal_file_operations');
    }
};
