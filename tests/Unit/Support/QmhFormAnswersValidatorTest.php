<?php

namespace Tests\Unit\Support;

use App\Support\QmhFormAnswersValidator;
use Tests\TestCase;

class QmhFormAnswersValidatorTest extends TestCase
{
    public function test_it_enforces_required_text_and_select_and_checkbox(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => true],
                ['id' => 'field_b', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'ok', 'label' => 'Sesuai'],
                    ['value' => 'nok', 'label' => 'Tidak Sesuai'],
                ]],
                ['id' => 'field_c', 'label' => 'Konfirmasi', 'type' => 'checkbox', 'required' => true],
            ],
        ];

        $answers = [
            'field_a' => '',
            'field_b' => 'invalid',
            'field_c' => false,
        ];

        $result = QmhFormAnswersValidator::validateAndNormalize($schema, $answers);

        $this->assertArrayHasKey('answers_json.field_a', $result['errors']);
        $this->assertArrayHasKey('answers_json.field_b', $result['errors']);
        $this->assertArrayHasKey('answers_json.field_c', $result['errors']);
    }

    public function test_it_normalizes_checkbox_from_string(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'agree', 'label' => 'Setuju', 'type' => 'checkbox', 'required' => false],
            ],
        ];

        $answers = [
            'agree' => '1',
        ];

        $result = QmhFormAnswersValidator::validateAndNormalize($schema, $answers);

        $this->assertSame([], $result['errors']);
        $this->assertSame(true, $result['normalized']['agree']);
    }

    public function test_it_accepts_list_as_array_and_checks_required(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'items', 'label' => 'Items', 'type' => 'list', 'required' => true],
            ],
        ];

        $resultEmpty = QmhFormAnswersValidator::validateAndNormalize($schema, ['items' => []]);
        $this->assertArrayHasKey('answers_json.items', $resultEmpty['errors']);

        $resultOk = QmhFormAnswersValidator::validateAndNormalize($schema, ['items' => ['A', '  ', 'B']]);
        $this->assertSame([], $resultOk['errors']);
        $this->assertSame(['A', 'B'], $resultOk['normalized']['items']);
    }

    public function test_it_rejects_invalid_number_value_even_when_not_required(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'required' => false],
            ],
        ];

        $result = QmhFormAnswersValidator::validateAndNormalize($schema, ['qty' => 'abc']);

        $this->assertArrayHasKey('answers_json.qty', $result['errors']);
        $this->assertSame('abc', $result['normalized']['qty']);
    }

    public function test_it_rejects_scientific_notation_for_number(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'required' => false],
            ],
        ];

        $result = QmhFormAnswersValidator::validateAndNormalize($schema, ['qty' => '1e3']);

        $this->assertArrayHasKey('answers_json.qty', $result['errors']);
    }

    public function test_it_preserves_number_string_formatting(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'required' => true],
            ],
        ];

        $result = QmhFormAnswersValidator::validateAndNormalize($schema, ['qty' => '1.50']);

        $this->assertSame([], $result['errors']);
        $this->assertSame('1.50', $result['normalized']['qty']);
    }
}
