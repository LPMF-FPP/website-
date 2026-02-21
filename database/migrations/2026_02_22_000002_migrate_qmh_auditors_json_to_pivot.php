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
                foreach ($audits as $audit) {
                    $auditorIds = $this->normalizeAuditorIds($audit->auditors_json);

                    foreach ($auditorIds as $auditorId) {
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
        DB::table('qmh_audit_auditors')->delete();
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
