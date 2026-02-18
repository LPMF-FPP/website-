<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qmh_templates')) {
            return;
        }

        $activeTemplates = DB::table('qmh_templates')
            ->where('is_active', true)
            ->orderBy('clause')
            ->orderBy('doc_type')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get([
                'id',
                'clause',
                'doc_type',
                'shell_mode',
                'orientation_policy',
                'show_signoff_footer',
            ]);

        $seenGroups = [];
        $archiveIds = [];

        foreach ($activeTemplates as $template) {
            $groupKey = implode('|', [
                (string) $template->clause,
                (string) $template->doc_type,
                trim((string) ($template->shell_mode ?? 'full')) ?: 'full',
                trim((string) ($template->orientation_policy ?? 'portrait')) ?: 'portrait',
                (string) ((bool) ($template->show_signoff_footer ?? true) ? '1' : '0'),
            ]);

            if (! array_key_exists($groupKey, $seenGroups)) {
                $seenGroups[$groupKey] = true;

                continue;
            }

            $archiveIds[] = (int) $template->id;
        }

        if ($archiveIds !== []) {
            DB::table('qmh_templates')
                ->whereIn('id', $archiveIds)
                ->update([
                    'is_active' => false,
                    'archived_at' => now(),
                ]);
        }

        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS qmh_templates_active_identity_unique_idx
ON qmh_templates (
    clause,
    doc_type,
    COALESCE(shell_mode, 'full'),
    COALESCE(orientation_policy, 'portrait'),
    COALESCE(show_signoff_footer, true)
)
WHERE is_active = true
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS qmh_templates_active_identity_unique_idx');
    }
};
