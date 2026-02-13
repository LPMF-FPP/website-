<?php

namespace Tests\Feature\Seeders;

use App\Models\QmhTemplate;
use Database\Seeders\QmhTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_qmh_template_seeder_does_not_create_baseline_templates(): void
    {
        $this->seed(QmhTemplateSeeder::class);

        $this->assertSame(0, QmhTemplate::query()->count());
    }

    public function test_qmh_template_seeder_is_idempotent(): void
    {
        $this->seed(QmhTemplateSeeder::class);
        $this->seed(QmhTemplateSeeder::class);

        $this->assertSame(0, QmhTemplate::query()->count());
    }
}
