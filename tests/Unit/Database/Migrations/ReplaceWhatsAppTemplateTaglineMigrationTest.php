<?php

namespace Tests\Unit\Database\Migrations;

use ReflectionMethod;
use Tests\TestCase;

class ReplaceWhatsAppTemplateTaglineMigrationTest extends TestCase
{
    public function test_recursive_replacement_changes_only_the_tagline(): void
    {
        $original = [
            'REQUEST_RECEIVED' => 'Terima kasih. Salam Presisi 🙏',
            'custom' => ['message' => 'Teks lain tetap, Salam Presisi.'],
            'count' => 3,
        ];

        $migration = require base_path('database/migrations/2026_10_15_000001_replace_whatsapp_template_tagline.php');
        $replaceInValue = new ReflectionMethod($migration, 'replaceInValue');
        $updated = $replaceInValue->invoke($migration, $original, 'Salam Presisi', 'Polri Bersama Masyarakat');

        $this->assertSame([
            'REQUEST_RECEIVED' => 'Terima kasih. Polri Bersama Masyarakat 🙏',
            'custom' => ['message' => 'Teks lain tetap, Polri Bersama Masyarakat.'],
            'count' => 3,
        ], $updated);

        $this->assertSame(3, $updated['count']);
    }
}
