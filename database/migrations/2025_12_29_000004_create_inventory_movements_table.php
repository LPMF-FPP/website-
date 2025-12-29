<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->enum('movement_type', ['RECEIPT', 'ISSUE', 'TRANSFER', 'ADJUST', 'DISPOSE', 'RETURN']);
            $table->string('reference_type')->nullable(); // PURCHASE, TEST_JOB, STOCKTAKE, MANUAL, DISPOSAL_DOC
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots');
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations');
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations');
            $table->decimal('qty', 14, 3);
            $table->string('uom');
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamp('performed_at')->useCurrent();
            $table->string('reason_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'lot_id']);
            $table->index(['from_location_id', 'to_location_id']);
            $table->index(['movement_type', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
