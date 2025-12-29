<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_print_logs', function (Blueprint $table) {
            $table->id();
            $table->string('label_type');  // 'evidence' or 'remaining'
            $table->morphs('printable');   // printable_type, printable_id
            $table->foreignId('printed_by')->constrained('users')->onDelete('cascade');
            $table->string('print_reason')->nullable();  // For reprints: 'damaged', 'lost', etc.
            $table->string('print_format')->default('a4');  // a4, single, etc.
            $table->integer('print_count')->default(1);
            $table->timestamps();

            // Note: morphs() already creates an index on printable_type, printable_id
            $table->index('label_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_print_logs');
    }
};
