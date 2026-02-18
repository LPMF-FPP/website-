<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QmhFormAnswersValidationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('quality.fr_v2.enabled', true);
        config()->set('quality.fr_v2.create_enabled', false);

        $this->createQmhPermissions();
    }

    public function test_create_fr_document_rejects_missing_required_answers_by_schema(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::factory()->create([
            'clause' => 4,
            'doc_type' => 'sop',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Required',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/required.docx',
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
        ]);

        Storage::disk('local')->put('qmh/templates/fr/required.docx', 'dummy');

        $response = $this->actingAs($user)->postJson('/api/quality/documents', [
            'doc_code' => 'QMH-FR-REQ-001',
            'title' => 'FR Required',
            'clause' => 4,
            'doc_type' => 'fr',
            'template_id' => $template->id,
            'parent_sop_id' => $parentSop->id,
            'answers_json' => [
                'field_a' => '',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.field_a']);
    }

    public function test_create_fr_document_rejects_invalid_number_answer_by_schema(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::factory()->create([
            'clause' => 4,
            'doc_type' => 'sop',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Number',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/number.docx',
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'required' => false],
                    ],
                ],
            ],
        ]);

        Storage::disk('local')->put('qmh/templates/fr/number.docx', 'dummy');

        $response = $this->actingAs($user)->postJson('/api/quality/documents', [
            'doc_code' => 'QMH-FR-NUM-001',
            'title' => 'FR Number',
            'clause' => 4,
            'doc_type' => 'fr',
            'template_id' => $template->id,
            'parent_sop_id' => $parentSop->id,
            'answers_json' => [
                'qty' => 'abc',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.qty']);
    }

    public function test_create_fr_document_rejects_scientific_notation_for_number(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::factory()->create([
            'clause' => 4,
            'doc_type' => 'sop',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Number Sci',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/number-sci.docx',
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'required' => false],
                    ],
                ],
            ],
        ]);

        Storage::disk('local')->put('qmh/templates/fr/number-sci.docx', 'dummy');

        $response = $this->actingAs($user)->postJson('/api/quality/documents', [
            'doc_code' => 'QMH-FR-NUM-SCI-001',
            'title' => 'FR Number Sci',
            'clause' => 4,
            'doc_type' => 'fr',
            'template_id' => $template->id,
            'parent_sop_id' => $parentSop->id,
            'answers_json' => [
                'qty' => '1e3',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.qty']);
    }

    public function test_create_fr_document_preserves_number_string_formatting_in_answers_json(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::factory()->create([
            'clause' => 4,
            'doc_type' => 'sop',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Number OK',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/number-ok.docx',
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'qty', 'label' => 'Jumlah', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
        ]);

        Storage::disk('local')->put('qmh/templates/fr/number-ok.docx', 'dummy');

        $response = $this->actingAs($user)->postJson('/api/quality/documents', [
            'doc_code' => 'QMH-FR-NUM-OK-001',
            'title' => 'FR Number OK',
            'clause' => 4,
            'doc_type' => 'fr',
            'template_id' => $template->id,
            'parent_sop_id' => $parentSop->id,
            'answers_json' => [
                'qty' => '1.50',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.current_revision.answers_json.qty', '1.50');
    }

    public function test_create_fr_document_uses_schema_override_for_validation_and_persists_snapshot(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::factory()->create([
            'clause' => 4,
            'doc_type' => 'sop',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Drift',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/drift.docx',
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
        ]);

        Storage::disk('local')->put('qmh/templates/fr/drift.docx', 'dummy');

        $overrideSchema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_b', 'label' => 'Kolom B', 'type' => 'text', 'required' => true],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-FR-OVR-001',
                'title' => 'FR Override',
                'clause' => 4,
                'doc_type' => 'fr',
                'template_id' => $template->id,
                'parent_sop_id' => $parentSop->id,
                'form_schema_json' => $overrideSchema,
                'answers_json' => [
                    'field_b' => 'OK',
                ],
            ])
            ->assertCreated();

        $document = QmhDocument::query()->where('doc_code', 'QMH-FR-OVR-001')->firstOrFail();
        $document->loadMissing('currentRevision');

        $this->assertEquals($overrideSchema, $document->currentRevision?->form_schema_json);
        $this->assertSame('OK', data_get($document->currentRevision?->answers_json, 'field_b'));
    }

    public function test_create_fr_document_rejects_missing_required_answers_by_schema_override(): void
    {
        Storage::fake('local');

        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $parentSop = QmhDocument::factory()->create([
            'clause' => 4,
            'doc_type' => 'sop',
        ]);

        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Drift 2',
            'clause' => 4,
            'doc_type' => 'fr',
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/drift-2.docx',
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
        ]);

        Storage::disk('local')->put('qmh/templates/fr/drift-2.docx', 'dummy');

        $overrideSchema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_b', 'label' => 'Kolom B', 'type' => 'text', 'required' => true],
            ],
        ];

        $this->actingAs($user)
            ->postJson('/api/quality/documents', [
                'doc_code' => 'QMH-FR-OVR-002',
                'title' => 'FR Override 2',
                'clause' => 4,
                'doc_type' => 'fr',
                'template_id' => $template->id,
                'parent_sop_id' => $parentSop->id,
                'form_schema_json' => $overrideSchema,
                'answers_json' => [
                    'field_b' => '',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.field_b']);
    }

    private function createQmhPermissions(): void
    {
        $viewPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.view'],
            [
                'display_name' => 'Lihat Quality Management Hub',
                'module' => 'qmh',
                'action' => 'view',
            ]
        );

        $createPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.create'],
            [
                'display_name' => 'Buat Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'create',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $viewPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $createPermission->id,
        ]);
    }
}
