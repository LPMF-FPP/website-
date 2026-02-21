<?php

namespace Tests\Feature\Quality;

use App\Models\QmhAudit;
use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QmhGovernanceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_cleanup_restores_auditors_json_from_pivot_data(): void
    {
        $creator = User::factory()->create();
        $auditor = User::factory()->create();

        $audit = QmhAudit::query()->create([
            'title' => 'Audit Rollback Cleanup',
            'audit_type' => 'internal',
            'status' => 'draft',
            'migration_phase' => 'pivot_only',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        DB::table('qmh_audit_auditors')->insert([
            'audit_id' => $audit->id,
            'user_id' => $auditor->id,
            'assigned_by' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_02_22_000007_rollback_cleanup_restore_json_column.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('qmh_audits', 'auditors_json'));

        $rawJson = DB::table('qmh_audits')->where('id', $audit->id)->value('auditors_json');
        $decoded = json_decode((string) $rawJson, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([$auditor->id], $decoded);
    }

    public function test_rollback_dual_compat_toggles_migration_phase(): void
    {
        $creator = User::factory()->create();

        $audit = QmhAudit::query()->create([
            'title' => 'Audit Rollback Phase',
            'audit_type' => 'internal',
            'status' => 'draft',
            'migration_phase' => 'pivot_only',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        $migration = require database_path('migrations/2026_02_22_000008_rollback_dual_compat_to_json_only.php');
        $migration->up();

        $this->assertDatabaseHas('qmh_audits', [
            'id' => $audit->id,
            'migration_phase' => 'dual',
        ]);

        $migration->down();

        $this->assertDatabaseHas('qmh_audits', [
            'id' => $audit->id,
            'migration_phase' => 'pivot_only',
        ]);
    }

    public function test_refresh_overdue_command_exits_when_lock_is_held(): void
    {
        $user = User::factory()->create();

        $rapat = QmhRapat::query()->create([
            'title' => 'Rapat Lock Test',
            'meeting_type' => 'ad_hoc',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $item = QmhRapatActionItem::query()->create([
            'rapat_id' => $rapat->id,
            'title' => 'Item untuk lock test',
            'status' => QmhRapatActionItem::STATUS_OPEN,
            'due_date' => now()->subDay()->toDateString(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $lock = Cache::lock('qmh-action-items-refresh-overdue', 300);
        $this->assertTrue($lock->get());

        try {
            Artisan::call('qmh:action-items:refresh-overdue');
        } finally {
            $lock->release();
        }

        $item->refresh();
        $this->assertSame(QmhRapatActionItem::STATUS_OPEN, $item->status);
    }
}
