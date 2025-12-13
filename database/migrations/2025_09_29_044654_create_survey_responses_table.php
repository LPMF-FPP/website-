<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_request_id')->constrained();

            // Rating questions (1-5 scale)
            $table->integer('service_quality')->unsigned();
            $table->integer('process_speed')->unsigned();
            $table->integer('staff_professionalism')->unsigned();
            $table->integer('facility_condition')->unsigned();
            $table->integer('overall_satisfaction')->unsigned();

            // Text feedback
            $table->text('suggestions')->nullable();
            $table->text('complaints')->nullable();
            $table->text('additional_comments')->nullable();

            // Respondent info (optional)
            $table->string('respondent_name')->nullable();
            $table->string('respondent_contact')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
