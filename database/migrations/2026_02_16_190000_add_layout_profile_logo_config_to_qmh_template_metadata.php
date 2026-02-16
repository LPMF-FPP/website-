<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('qmh_templates')
            ->select(['id', 'metadata'])
            ->where('doc_type', 'fr')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = $this->decodeMetadata($row->metadata ?? null);
                    if ($metadata === null) {
                        continue;
                    }

                    $before = $metadata;

                    if (! array_key_exists('logo_source', $metadata)) {
                        $metadata['logo_source'] = 'settings';
                    }

                    if (! array_key_exists('risk_matrix_columns', $metadata) && ($metadata['layout_profile'] ?? null) === 'risk_matrix') {
                        $metadata['risk_matrix_columns'] = ['Aspek Risiko', 'Nilai Risiko', 'Keterangan'];
                    }

                    if ($metadata === $before) {
                        continue;
                    }

                    DB::table('qmh_templates')
                        ->where('id', $row->id)
                        ->update([
                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally no-op to avoid destructive rollback on metadata keys
        // that may be edited manually after migration is applied.
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeMetadata(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return null;
            }
        }

        return [];
    }
};
