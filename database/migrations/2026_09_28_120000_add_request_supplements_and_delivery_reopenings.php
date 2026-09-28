<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_requests', function (Blueprint $table): void {
            $table->foreignId('parent_test_request_id')
                ->nullable()
                ->after('id')
                ->constrained('test_requests')
                ->restrictOnDelete();
            $table->text('supplement_reason')->nullable()->after('case_description');
            $table->index(['parent_test_request_id', 'created_at'], 'test_requests_parent_created_idx');
        });

        Schema::table('deliveries', function (Blueprint $table): void {
            $table->unsignedSmallInteger('handover_cycle')->default(1)->after('status');
        });

        Schema::create('delivery_reopenings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_id')->constrained()->restrictOnDelete();
            $table->foreignId('sample_id')->constrained()->restrictOnDelete();
            $table->foreignId('reopened_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('handover_cycle');
            $table->string('previous_request_status', 40);
            $table->string('previous_delivery_status', 40)->nullable();
            $table->timestamp('previous_delivery_date')->nullable();
            $table->timestamp('previous_ready_for_delivery_at')->nullable();
            $table->timestamp('previous_completed_at')->nullable();
            $table->text('reason');
            $table->json('superseded_document_ids')->nullable();
            $table->json('superseded_message_log_ids')->nullable();
            $table->timestamp('reopened_at');
            $table->timestamps();

            $table->unique(['test_request_id', 'handover_cycle'], 'delivery_reopenings_request_cycle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_reopenings');

        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropColumn('handover_cycle');
        });

        Schema::table('test_requests', function (Blueprint $table): void {
            $table->dropIndex('test_requests_parent_created_idx');
            $table->dropConstrainedForeignId('parent_test_request_id');
            $table->dropColumn('supplement_reason');
        });
    }
};
