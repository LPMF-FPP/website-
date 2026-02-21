<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_kums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedSmallInteger('year');
            $table->enum('period', ['q1', 'q2', 'q3', 'q4', 'annual'])->default('annual');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('location')->nullable();
            $table->longText('agenda')->nullable();
            $table->longText('minutes_content')->nullable();
            $table->json('participants_json')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'closed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['year', 'period'], 'qmh_kum_year_period_unique');
            $table->index(['status', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_kums');
    }
};
