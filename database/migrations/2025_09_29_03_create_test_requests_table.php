<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('investigator_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('suspect_name');
            $table->string('suspect_gender')->nullable();
            $table->integer('suspect_age')->nullable();
            $table->text('suspect_address')->nullable();
            $table->string('case_number')->nullable();
            $table->text('case_description')->nullable();
            $table->date('incident_date')->nullable();
            $table->text('incident_location')->nullable();
            $table->enum('status', [
                'submitted', 'verified', 'received', 'in_testing', 
                'analysis', 'quality_check', 'ready_for_delivery', 'completed'
            ])->default('submitted');
            $table->string('official_letter_path')->nullable();
            $table->string('evidence_photo_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_requests');
    }
};
