<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_rapats', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('meeting_type', ['mingguan', 'bulanan', 'ad_hoc'])->default('ad_hoc');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('location')->nullable();
            $table->longText('agenda')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['meeting_type', 'status']);
            $table->index('scheduled_at');
        });

        Schema::create('qmh_rapat_pesertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('qmh_rapats')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('attendance_status', ['hadir', 'tidak_hadir', 'izin'])->default('hadir');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rapat_id', 'user_id'], 'qmh_rapat_peserta_unique');
            $table->index('attendance_status');
        });

        Schema::create('qmh_rapat_notulensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('qmh_rapats')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->longText('content')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rapat_id', 'version'], 'qmh_rapat_notulensi_unique');
        });

        Schema::create('qmh_rapat_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapat_id')->constrained('qmh_rapats')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'verified', 'closed', 'overdue'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_rapat_action_items');
        Schema::dropIfExists('qmh_rapat_notulensis');
        Schema::dropIfExists('qmh_rapat_pesertas');
        Schema::dropIfExists('qmh_rapats');
    }
};
