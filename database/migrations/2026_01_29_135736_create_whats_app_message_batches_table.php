<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // 'broadcast', 'reminder', 'notification'
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->text('message_preview')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->boolean('mention_all')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_batches');
    }
};
