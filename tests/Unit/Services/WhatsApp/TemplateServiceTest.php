<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\NumberingService;
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

    public function test_template_labels_include_consolidated_report_templates(): void
    {
        $labels = $this->service->getTemplateLabels();

        $this->assertArrayHasKey('CONSOLIDATED_BIWEEKLY', $labels['system']);
        $this->assertArrayHasKey('CONSOLIDATED_MONTHLY', $labels['system']);
        $this->assertArrayHasKey('CONSOLIDATED_QUARTERLY', $labels['system']);
    }

    public function test_consolidated_template_placeholders_include_report_url(): void
    {
        $placeholders = $this->service->getPlaceholders('system', 'CONSOLIDATED_BIWEEKLY');

        $this->assertContains('period_label', $placeholders);
        $this->assertContains('report_url', $placeholders);
    }

    public function test_consolidated_template_preview_uses_public_lpmf_report_url(): void
    {
        $preview = $this->service->preview('system', 'CONSOLIDATED_BIWEEKLY');

        $this->assertStringContainsString('https://lpmf.web.id/statistics?tab=reports', $preview);
    }

    public function test_status_report_preview_uses_current_tracking_number_format(): void
    {
        $expectedResi = app(NumberingService::class)->example('tracking');

        $preview = $this->service->preview('command', 'STATUS_REPORT');

        $this->assertStringContainsString($expectedResi, $preview);
    }

    public function test_help_preview_uses_current_tracking_number_format(): void
    {
        $expectedResi = app(NumberingService::class)->example('tracking');

        $preview = $this->service->preview('command', 'HELP');

        $this->assertStringContainsString("/resi {$expectedResi}", $preview);
    }

    public function test_resi_format_error_preview_uses_current_tracking_number_format(): void
    {
        $expectedResi = app(NumberingService::class)->example('tracking');

        $preview = $this->service->preview('command', 'RESI_FORMAT_ERROR');

        $this->assertStringContainsString("/resi {$expectedResi}", $preview);
    }
}
