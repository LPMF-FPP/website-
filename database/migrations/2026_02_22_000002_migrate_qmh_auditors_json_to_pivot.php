<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('qmh_audits')
            ->select(['id', 'created_by', 'auditors_json'])
            ->orderBy('id')
            ->chunkById(100, function ($audits): void {
                $auditorIdsByAudit = [];
                $candidateUserIds = [];

                foreach ($audits as $audit) {
                    $auditorIds = $this->normalizeAuditorIds($audit->auditors_json);

                    $auditorIdsByAudit[(int) $audit->id] = $auditorIds;
                    $candidateUserIds = array_merge($candidateUserIds, $auditorIds);
                }

                $validUserIdMap = [];

                if (! empty($candidateUserIds)) {
                    $validUserIdMap = DB::table('users')
                        ->whereIn('id', array_values(array_unique($candidateUserIds)))
                        ->pluck('id')
                        ->mapWithKeys(fn ($id) => [(int) $id => true])
                        ->all();
                }

                foreach ($audits as $audit) {
                    $validAuditorIds = array_values(array_filter(
                        $auditorIdsByAudit[(int) $audit->id] ?? [],
                        fn (int $auditorId) => isset($validUserIdMap[$auditorId])
                    ));

                    foreach ($validAuditorIds as $auditorId) {
                        DB::table('qmh_audit_auditors')->updateOrInsert(
                            [
                                'audit_id' => (int) $audit->id,
                                'user_id' => $auditorId,
                            ],
                            [
                                'assigned_by' => $audit->created_by ? (int) $audit->created_by : null,
                                'deleted_at' => null,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        // no-op to avoid deleting assignment data created after backfill
    }

    /**
     * @return array<int>
     */
    private function normalizeAuditorIds(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn (mixed $value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
};
