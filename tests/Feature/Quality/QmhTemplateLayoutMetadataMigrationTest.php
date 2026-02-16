<?php

namespace Tests\Feature\Quality;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QmhTemplateLayoutMetadataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_up_sets_logo_source_and_is_idempotent_without_forcing_layout_profile(): void
    {
        DB::table('qmh_templates')->insert([
            'name' => 'Template FR Legacy',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => json_encode([
                'content_html' => '<p>Legacy</p>',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_02_16_190000_add_layout_profile_logo_config_to_qmh_template_metadata.php');

        $migration->up();
        $first = $this->templateMetadata();

        $this->assertSame('settings', $first['logo_source'] ?? null);
        $this->assertFalse(array_key_exists('layout_profile', $first));

        $migration->up();
        $second = $this->templateMetadata();

        $this->assertSame($first, $second);
    }

    public function test_migration_down_is_noop_and_not_destructive(): void
    {
        DB::table('qmh_templates')->insert([
            'name' => 'Template FR Configured',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => null,
            'is_active' => true,
            'metadata' => json_encode([
                'layout_profile' => 'risk_matrix',
                'logo_source' => 'custom',
                'logo_path' => 'images/logo.png',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_02_16_190000_add_layout_profile_logo_config_to_qmh_template_metadata.php');

        $before = $this->templateMetadata();
        $migration->down();
        $after = $this->templateMetadata();

        $this->assertSame($before, $after);
        $this->assertSame('risk_matrix', $after['layout_profile'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateMetadata(): array
    {
        $row = DB::table('qmh_templates')->first();
        $raw = $row?->metadata;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
