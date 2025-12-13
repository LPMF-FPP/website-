<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->text('physical_identification')->nullable()->after('active_substance');
            $table->decimal('quantity', 10, 2)->nullable()->after('weight');
            $table->string('quantity_unit', 50)->nullable()->after('quantity');
            $table->string('batch_number', 100)->nullable()->after('quantity_unit');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->string('test_type')->nullable()->after('test_methods');
            $table->text('notes')->nullable()->after('test_type');
            $table->foreignId('assigned_analyst_id')->nullable()->after('condition')->constrained('users');
            $table->date('test_date')->nullable()->after('assigned_analyst_id');
            $table->string('status')->default('pending')->after('test_date');
        });
    }

    public function down(): void
    {
        Schema::table('samples', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_analyst_id');
            $table->dropColumn([
                'physical_identification',
                'quantity',
                'quantity_unit',
                'batch_number',
                'expiry_date',
                'test_type',
                'notes',
                'test_date',
                'status',
            ]);
        });
    }
};
