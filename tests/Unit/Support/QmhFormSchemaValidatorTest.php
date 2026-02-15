<?php

namespace Tests\Unit\Support;

use App\Support\QmhFormSchemaValidator;
use Tests\TestCase;

class QmhFormSchemaValidatorTest extends TestCase
{
    public function test_it_accepts_valid_schema(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'section_a', 'label' => 'Bagian A', 'type' => 'section', 'required' => false],
                ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => true],
                ['id' => 'field_b', 'label' => 'Kolom B', 'type' => 'textarea', 'required' => false],
                ['id' => 'field_c', 'label' => 'Pilihan', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'ok', 'label' => 'Sesuai'],
                    ['value' => 'nok', 'label' => 'Tidak Sesuai'],
                ]],
                ['id' => 'field_d', 'label' => 'Centang', 'type' => 'checkbox', 'required' => false],
                ['id' => 'field_e', 'label' => 'Tanggal', 'type' => 'date', 'required' => false],
                ['id' => 'field_f', 'label' => 'Angka', 'type' => 'number', 'required' => false],
                ['id' => 'field_g', 'label' => 'List', 'type' => 'list', 'required' => false],
            ],
        ];

        $errors = QmhFormSchemaValidator::errors($schema);

        $this->assertSame([], $errors);
    }

    public function test_it_rejects_duplicate_ids_and_bad_select_options(): void
    {
        $schema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_a', 'label' => 'A', 'type' => 'text', 'required' => false],
                ['id' => 'field_a', 'label' => 'A2', 'type' => 'text', 'required' => false],
                ['id' => 'bad-id', 'label' => 'Bad', 'type' => 'text', 'required' => false],
                ['id' => 'sel', 'label' => 'Sel', 'type' => 'select', 'required' => true, 'options' => [
                    ['value' => 'ok', 'label' => 'Sesuai'],
                    ['value' => 'ok', 'label' => 'Dupe'],
                ]],
            ],
        ];

        $errors = QmhFormSchemaValidator::errors($schema);

        $this->assertNotEmpty($errors);
    }
}
