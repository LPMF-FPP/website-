<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\TemplateService;
use Tests\TestCase;

class TemplateServiceTest extends TestCase
{
    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService;
    }

    public function test_get_magic_variables_returns_grouped_array(): void
    {
        $variables = $this->service->getMagicVariables();

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('Global', $variables);
        $this->assertArrayHasKey('Penyidik', $variables);
        $this->assertArrayHasKey('Perkara', $variables);
        $this->assertArrayHasKey('Sampel', $variables);
        $this->assertArrayHasKey('Status', $variables);
    }

    public function test_get_magic_variables_global_group_contains_greetings(): void
    {
        $variables = $this->service->getMagicVariables();

        $this->assertContains('greetings', $variables['Global']);
        $this->assertContains('timestamp', $variables['Global']);
    }

    public function test_get_magic_variables_penyidik_group_contains_investigator_fields(): void
    {
        $variables = $this->service->getMagicVariables();

        $this->assertContains('nama', $variables['Penyidik']);
        $this->assertContains('pangkat', $variables['Penyidik']);
        $this->assertContains('phone', $variables['Penyidik']);
    }

    public function test_get_magic_variables_perkara_group_contains_case_fields(): void
    {
        $variables = $this->service->getMagicVariables();

        $this->assertContains('nomor surat', $variables['Perkara']);
        $this->assertContains('tersangka', $variables['Perkara']);
        $this->assertContains('resi', $variables['Perkara']);
    }

    public function test_get_magic_variables_sampel_group_contains_sample_fields(): void
    {
        $variables = $this->service->getMagicVariables();

        $this->assertContains('sample_count', $variables['Sampel']);
    }

    public function test_get_magic_variables_status_group_contains_status_fields(): void
    {
        $variables = $this->service->getMagicVariables();

        $this->assertContains('current_status', $variables['Status']);
    }

    public function test_get_magic_variables_returns_unique_values_per_group(): void
    {
        $variables = $this->service->getMagicVariables();

        foreach ($variables as $group => $items) {
            $unique = array_unique($items);
            $this->assertCount(
                count($items),
                $unique,
                "Group '$group' contains duplicate values"
            );
        }
    }
}
