<?php

namespace Tests\Unit\Support;

use App\Support\QmhAnswerSanitizer;
use Tests\TestCase;

class QmhAnswerSanitizerTest extends TestCase
{
    public function test_sanitize_rich_text_removes_xml_prolog(): void
    {
        $input = '<?xml encoding="utf-8" ?><p>Tujuan <strong>uji</strong></p>';

        $sanitized = QmhAnswerSanitizer::sanitizeRichText($input);

        $this->assertSame('<p>Tujuan <strong>uji</strong></p>', $sanitized);
        $this->assertStringNotContainsString('<?xml', $sanitized);
    }

    public function test_sanitize_answers_json_removes_xml_prolog_from_string_and_array_items(): void
    {
        $input = [
            'purpose' => '<?xml encoding="utf-8" ?><p>Tujuan baru</p>',
            'records' => [
                '<?xml encoding="utf-8" ?><p>FR-001</p>',
                'FR-002',
            ],
        ];

        $sanitized = QmhAnswerSanitizer::sanitizeAnswersJson($input);

        $this->assertArrayHasKey('purpose', $sanitized);
        $this->assertSame('<p>Tujuan baru</p>', $sanitized['purpose']);
        $this->assertSame(['<p>FR-001</p>', 'FR-002'], $sanitized['records']);
        $this->assertStringNotContainsString('<?xml', $sanitized['purpose']);
    }
}
