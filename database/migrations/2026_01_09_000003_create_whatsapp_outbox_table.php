<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_request_id')->nullable()->constrained('test_requests')->nullOnDelete();
            $table->string('milestone_key');
            $table->string('to_phone_e164');
            $table->string('to_jid')->nullable();
            $table->text('message_text');
            $table->string('provider_message_id')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed', 'delivered', 'read'])->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['test_request_id', 'milestone_key'], 'unique_milestone_notification');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_outbox');
    }
};
