<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const READINESS_CATEGORIES = [
        'permission_grants',
        'lot_item_policies',
        'rehearsal',
        'postgresql_guards',
        'backup_restore',
        'monitoring_escalation',
        'disable_rollback',
    ];

    public function up(): void
    {
        Schema::table('inventory_opening_baselines', function (Blueprint $table): void {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('submission_revision')->default(0);
        });
        Schema::table('inventory_mutation_mode_events', function (Blueprint $table): void {
            $table->string('evidence_reference', 255)->nullable();
        });

        Schema::create('inventory_opening_baseline_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('baseline_id')->constrained('inventory_opening_baselines')->restrictOnDelete();
            $table->unsignedInteger('submission_revision');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('decision_note')->nullable();
            $table->string('source_fingerprint', 64);
            $table->string('resulting_source_fingerprint', 64)->nullable();
            $table->json('approved_lines_snapshot');
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['baseline_id', 'submission_revision'], 'inventory_opening_baseline_approval_revision_unique');
        });

        Schema::create('inventory_readiness_evidence', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 40);
            $table->unsignedInteger('version');
            $table->string('evidence_reference', 255);
            $table->string('state_fingerprint', 64)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['category', 'version'], 'inventory_readiness_evidence_version_unique');
        });

        Schema::create('inventory_readiness_signoffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_id')->constrained('inventory_readiness_evidence')->restrictOnDelete();
            $table->string('signoff_role', 32);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['evidence_id', 'signoff_role'], 'inventory_readiness_signoff_role_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_opening_baselines DROP CONSTRAINT IF EXISTS inventory_opening_baselines_status_check');
            DB::statement("ALTER TABLE inventory_opening_baselines ADD CONSTRAINT inventory_opening_baselines_status_check CHECK (status IN ('blocked', 'stale', 'draft', 'submitted', 'approved'))");
            DB::statement("ALTER TABLE inventory_opening_baseline_approvals ADD CONSTRAINT inventory_opening_baseline_approvals_decision_check CHECK (decision IN ('approved', 'returned'))");
            DB::statement("ALTER TABLE inventory_opening_baseline_approvals ADD CONSTRAINT inventory_opening_baseline_approvals_result_fingerprint_check CHECK ((decision = 'approved' AND resulting_source_fingerprint IS NOT NULL) OR (decision = 'returned' AND resulting_source_fingerprint IS NULL))");
            DB::statement("CREATE UNIQUE INDEX inventory_opening_baselines_single_approved ON inventory_opening_baselines ((status)) WHERE status = 'approved'");
            DB::statement("ALTER TABLE inventory_readiness_evidence ADD CONSTRAINT inventory_readiness_evidence_category_check CHECK (category IN ('permission_grants', 'lot_item_policies', 'rehearsal', 'postgresql_guards', 'backup_restore', 'monitoring_escalation', 'disable_rollback'))");
            DB::statement("ALTER TABLE inventory_readiness_signoffs ADD CONSTRAINT inventory_readiness_signoffs_role_check CHECK (signoff_role IN ('inventory_administrator', 'system_owner'))");
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_approval_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'inventory approval evidence is append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_opening_baseline_approvals_append_only
                    BEFORE UPDATE OR DELETE ON inventory_opening_baseline_approvals
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_approval_mutation();
                CREATE TRIGGER inventory_opening_baseline_approvals_no_truncate
                    BEFORE TRUNCATE ON inventory_opening_baseline_approvals
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_approval_mutation();
                CREATE TRIGGER inventory_readiness_evidence_append_only
                    BEFORE UPDATE OR DELETE ON inventory_readiness_evidence
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_approval_mutation();
                CREATE TRIGGER inventory_readiness_evidence_no_truncate
                    BEFORE TRUNCATE ON inventory_readiness_evidence
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_approval_mutation();
                CREATE TRIGGER inventory_readiness_signoffs_append_only
                    BEFORE UPDATE OR DELETE ON inventory_readiness_signoffs
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_approval_mutation();
                CREATE TRIGGER inventory_readiness_signoffs_no_truncate
                    BEFORE TRUNCATE ON inventory_readiness_signoffs
                    FOR EACH STATEMENT EXECUTE FUNCTION prevent_inventory_approval_mutation();
                SQL);
        }

        DB::table('permissions')->insertOrIgnore([
            ['name' => 'inventori.mutation.disable', 'display_name' => 'Nonaktifkan Mutasi Inventori Darurat', 'module' => 'inventori', 'action' => 'mutation.disable', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'inventori.mutation.activate', 'display_name' => 'Aktifkan Mutasi Inventori', 'module' => 'inventori', 'action' => 'mutation.activate', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'inventori.readiness.inventory-admin.accept', 'display_name' => 'Terima Bukti Kesiapan sebagai Administrator Inventori', 'module' => 'inventori', 'action' => 'readiness.inventory-admin.accept', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'inventori.readiness.system-owner.accept', 'display_name' => 'Terima Bukti Kesiapan sebagai Pemilik Sistem', 'module' => 'inventori', 'action' => 'readiness.system-owner.accept', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS inventory_opening_baselines_single_approved');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_opening_baseline_approvals_append_only ON inventory_opening_baseline_approvals');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_opening_baseline_approvals_no_truncate ON inventory_opening_baseline_approvals');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_readiness_evidence_append_only ON inventory_readiness_evidence');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_readiness_evidence_no_truncate ON inventory_readiness_evidence');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_readiness_signoffs_append_only ON inventory_readiness_signoffs');
            DB::unprepared('DROP TRIGGER IF EXISTS inventory_readiness_signoffs_no_truncate ON inventory_readiness_signoffs');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_approval_mutation()');
            DB::statement('ALTER TABLE inventory_readiness_signoffs DROP CONSTRAINT IF EXISTS inventory_readiness_signoffs_role_check');
            DB::statement('ALTER TABLE inventory_readiness_evidence DROP CONSTRAINT IF EXISTS inventory_readiness_evidence_category_check');
            DB::statement('ALTER TABLE inventory_opening_baselines DROP CONSTRAINT IF EXISTS inventory_opening_baselines_status_check');
            DB::statement("ALTER TABLE inventory_opening_baselines ADD CONSTRAINT inventory_opening_baselines_status_check CHECK (status IN ('blocked', 'stale', 'draft'))");
            DB::statement('ALTER TABLE inventory_opening_baseline_approvals DROP CONSTRAINT IF EXISTS inventory_opening_baseline_approvals_decision_check');
            DB::statement('ALTER TABLE inventory_opening_baseline_approvals DROP CONSTRAINT IF EXISTS inventory_opening_baseline_approvals_result_fingerprint_check');
        }

        Schema::dropIfExists('inventory_readiness_signoffs');
        Schema::dropIfExists('inventory_readiness_evidence');
        Schema::dropIfExists('inventory_opening_baseline_approvals');
        Schema::table('inventory_opening_baselines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
            $table->dropColumn('submission_revision');
        });
        Schema::table('inventory_mutation_mode_events', function (Blueprint $table): void {
            $table->dropColumn('evidence_reference');
        });
    }
};
