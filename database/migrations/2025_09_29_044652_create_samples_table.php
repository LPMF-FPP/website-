<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_code')->unique();
            $table->foreignId('test_request_id')->constrained();
            $table->string('sample_name');
            $table->text('sample_description')->nullable();
            $table->enum('sample_form', ['powder', 'pill', 'liquid', 'plant', 'crystal', 'paste', 'capsule', 'other'])->default('other');
            $table->enum('sample_category', [
                'narkotika', 'psikotropika', 'prekursor', 'zat_adiktif', 
                'obat_keras', 'other'
            ])->default('other');
            $table->string('sample_color')->nullable();
            $table->decimal('sample_weight', 10, 2)->nullable();
            $table->integer('package_quantity')->default(1);
            $table->string('net_weight')->nullable();
            $table->string('packaging_type')->nullable();
            $table->text('storage_location')->nullable();
            $table->enum('condition', ['baik', 'rusak', 'basah', 'kering'])->default('baik');
            $table->text('photo_path')->nullable();
            $table->text('receipt_path')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->enum('sample_status', [
                'received', 'in_queue', 'in_testing', 'tested', 
                'in_analysis', 'analysis_complete', 'quality_check', 
                'qc_approved', 'qc_rejected', 'ready_for_delivery', 'delivered'
            ])->default('received');
            $table->text('test_methods')->nullable();
            $table->string('active_substance')->nullable();
            $table->text('testing_notes')->nullable();
            $table->foreignId('tested_by')->nullable()->constrained('users');
            $table->timestamp('testing_started_at')->nullable();
            $table->timestamp('testing_completed_at')->nullable();
            $table->string('other_sample_category')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};
