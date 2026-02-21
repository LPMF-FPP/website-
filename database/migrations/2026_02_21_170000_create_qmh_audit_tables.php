<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_audits', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('audit_type', ['internal', 'eksternal', 'surveillance'])->default('internal');
            $table->text('scope')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('location')->nullable();
            $table->json('auditors_json')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'closed', 'cancelled'])->default('draft');
            $table->longText('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['audit_type', 'status']);
            $table->index('scheduled_at');
        });

        Schema::create('qmh_audit_temuans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('qmh_audits')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('severity', ['minor', 'major', 'kritis'])->default('minor');
            $table->text('corrective_action')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['severity', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_audit_temuans');
        Schema::dropIfExists('qmh_audits');
    }
};
