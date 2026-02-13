<?php

namespace Tests\Feature\Seeders;

use App\Models\QmhTemplate;
use Database\Seeders\QmhTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_qmh_template_seeder_creates_baseline_templates_for_all_clause_doc_type_combinations(): void
    {
        $this->seed(QmhTemplateSeeder::class);

        $this->assertSame(15, QmhTemplate::query()->count());
        $this->assertSame(15, QmhTemplate::query()->where('is_active', true)->count());
        $this->assertSame(15, QmhTemplate::query()->where('version', 1)->count());

        $this->assertDatabaseHas('qmh_templates', [
            'clause' => 4,
            'doc_type' => 'sop',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('qmh_templates', [
            'clause' => 8,
            'doc_type' => 'fr',
            'is_active' => true,
        ]);
    }

    public function test_qmh_template_seeder_is_idempotent(): void
    {
        $this->seed(QmhTemplateSeeder::class);
        $this->seed(QmhTemplateSeeder::class);

        $this->assertSame(15, QmhTemplate::query()->count());
    }
}
