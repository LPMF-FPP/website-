<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            // Existing batch logs remain valid, while direct sends can now share this envelope.
            $table->unsignedBigInteger('batch_id')->nullable()->change();
            $table->string('transport', 20)->nullable();
            $table->text('payload_encrypted')->nullable();
            $table->string('attachment_disk', 50)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_filename')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_label')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->boolean('retryable')->default(false);
            $table->text('retry_block_reason')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'created_at']);
            $table->unique('idempotency_key');
        });

        Schema::create('whatsapp_message_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_message_log_id')
                ->constrained('whatsapp_message_logs')
                ->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 20);
            $table->unsignedSmallInteger('provider_status')->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_message_log_id', 'attempt_number'], 'whatsapp_message_attempt_number_unique');
            $table->index(['whatsapp_message_log_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_attempts');

        DB::transaction(function (): void {
            $directMessages = DB::table('whatsapp_message_logs')->whereNull('batch_id');
            $totalRecipients = (clone $directMessages)->count();

            if ($totalRecipients === 0) {
                return;
            }

            $sentCount = (clone $directMessages)->where('status', 'sent')->count();
            $now = now();
            $batchId = DB::table('whatsapp_message_batches')->insertGetId([
                'type' => 'legacy_outbound',
                'title' => 'Riwayat WhatsApp tanpa batch',
                'total_recipients' => $totalRecipients,
                'sent_count' => $sentCount,
                'failed_count' => $totalRecipients - $sentCount,
                'mention_all' => false,
                'started_at' => $now,
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $directMessages->update([
                'batch_id' => $batchId,
                'updated_at' => $now,
            ]);
        });

        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'transport',
                'payload_encrypted',
                'attachment_disk',
                'attachment_path',
                'attachment_filename',
                'attachment_mime',
                'attachment_size',
                'source_type',
                'source_id',
                'source_label',
                'idempotency_key',
                'retryable',
                'retry_block_reason',
                'attempt_count',
                'claimed_at',
                'completed_at',
            ]);
            $table->unsignedBigInteger('batch_id')->nullable(false)->change();
        });
    }
};
