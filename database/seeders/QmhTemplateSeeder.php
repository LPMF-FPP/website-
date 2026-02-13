<?php

namespace Database\Seeders;

use App\Models\QmhTemplate;
use Illuminate\Database\Seeder;

class QmhTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $clauses = [4, 5, 6, 7, 8];
        $docTypes = ['sop', 'ik', 'fr'];

        foreach ($clauses as $clause) {
            foreach ($docTypes as $docType) {
                QmhTemplate::query()->firstOrCreate(
                    [
                        'clause' => $clause,
                        'doc_type' => $docType,
                        'version' => 1,
                    ],
                    [
                        'name' => sprintf('Template %s Klausul %d (Baseline)', strtoupper($docType), $clause),
                        'storage_disk' => 'local',
                        'source_docx_path' => null,
                        'is_active' => true,
                        'metadata' => [
                            'seeded_by' => self::class,
                            'seeded_at' => now()->toIso8601String(),
                        ],
                    ]
                );
            }
        }
    }
}
