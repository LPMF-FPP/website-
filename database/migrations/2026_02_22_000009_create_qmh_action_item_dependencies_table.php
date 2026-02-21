<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qmh_action_item_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_item_id')->constrained('qmh_rapat_action_items')->cascadeOnDelete();
            $table->foreignId('depends_on_action_item_id')->constrained('qmh_rapat_action_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['action_item_id', 'depends_on_action_item_id'], 'qmh_action_item_dependency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qmh_action_item_dependencies');
    }
};
