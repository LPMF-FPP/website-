<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_request_id')->constrained()->cascadeOnDelete();
            $table->string('respondent_name');
            $table->string('respondent_job_title');
            $table->string('respondent_institution');
            $table->enum('respondent_job_category', [
                'TNI',
                'Polri',
                'ASN',
                'Swasta',
                'Wirausaha',
                'Mahasiswa',
                'Siswa',
            ]);
            $table->enum('request_type', ['Kimia - Fisika', 'Mikrobiologi']);
            $table->boolean('voluntary_statement');
            $table->json('answers');
            $table->text('suggestion');
            $table->text('complaint')->nullable();
            $table->text('follow_up')->nullable();
            $table->decimal('score_avg', 5, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('test_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_surveys');
    }
};
