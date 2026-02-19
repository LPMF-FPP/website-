<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->string('source_module', 50)->nullable()->after('test_request_id');
            $table->string('source_ref_type', 100)->nullable()->after('source_module');
            $table->unsignedBigInteger('source_ref_id')->nullable()->after('source_ref_type');
            $table->string('workflow_stage', 50)->nullable()->after('source_ref_id');
            $table->string('action_token_hash', 128)->nullable()->after('workflow_stage');
            $table->timestamp('action_expires_at')->nullable()->after('action_token_hash');
            $table->timestamp('token_consumed_at')->nullable()->after('action_expires_at');
            $table->json('context_json')->nullable()->after('token_consumed_at');

            $table->index(['source_module', 'source_ref_type', 'source_ref_id'], 'staff_tasks_source_ref_idx');
            $table->index(['source_module', 'workflow_stage', 'assigned_to'], 'staff_tasks_stage_assignee_idx');
            $table->index('action_expires_at', 'staff_tasks_action_expires_idx');
        });

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS staff_tasks_qmh_active_unique_idx ON staff_tasks (source_module, source_ref_type, source_ref_id, workflow_stage, assigned_to) WHERE deleted_at IS NULL AND status IN ('pending', 'in_progress')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS staff_tasks_qmh_active_unique_idx');

        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->dropIndex('staff_tasks_source_ref_idx');
            $table->dropIndex('staff_tasks_stage_assignee_idx');
            $table->dropIndex('staff_tasks_action_expires_idx');

            $table->dropColumn([
                'source_module',
                'source_ref_type',
                'source_ref_id',
                'workflow_stage',
                'action_token_hash',
                'action_expires_at',
                'token_consumed_at',
                'context_json',
            ]);
        });
    }
};
