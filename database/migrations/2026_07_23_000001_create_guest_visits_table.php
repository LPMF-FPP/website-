<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigator_id')->constrained('investigators')->restrictOnDelete();
            $table->foreignId('test_request_id')->nullable()->constrained('test_requests')->nullOnDelete();
            $table->date('visit_date');
            $table->time('visit_time');
            $table->string('purpose');
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_identity')->nullable();
            $table->string('visitor_relation')->nullable();
            $table->string('visitor_phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('check_out_at')->nullable();
            $table->boolean('nda_accepted')->default(false);
            $table->timestamp('nda_accepted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visit_date']);
            $table->index('investigator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_visits');
    }
};
