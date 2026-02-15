<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Quality\QmhDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_unauthenticated_user_cannot_lock_revision(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $response = $this->postJson("/api/quality/revisions/{$revision->id}/lock");

        $response->assertUnauthorized();
    }

    public function test_lock_conflict_returns_409_for_another_user(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($owner);

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($other)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertStatus(409);
    }

    public function test_only_lock_owner_can_send_heartbeat(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($owner);

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($other)
            ->postJson("/api/quality/revisions/{$revision->id}/heartbeat")
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/heartbeat")
            ->assertOk();
    }

    public function test_force_unlock_requires_reason_and_records_force_unlocker(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $forcer */
        $forcer = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($owner);

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($forcer)
            ->postJson("/api/quality/revisions/{$revision->id}/unlock", [
                'force' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($forcer)
            ->postJson("/api/quality/revisions/{$revision->id}/unlock", [
                'force' => true,
                'reason' => 'Emergency handover',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_locks', [
            'revision_id' => $revision->id,
            'force_unlocked_by' => $forcer->id,
            'force_unlocked_reason' => 'Emergency handover',
        ]);
    }

    public function test_submit_requires_lock_owner_and_moves_revision_to_in_review(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($other)
            ->postJson("/api/quality/revisions/{$revision->id}/submit", [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertForbidden();

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/submit", [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'in_review',
            'diperiksa_oleh' => $reviewer->id,
        ]);
    }

    public function test_review_endpoint_supports_return_and_pass_paths_with_sod_rules(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)->postJson("/api/quality/revisions/{$revision->id}/lock")->assertOk();
        $this->actingAs($creator)->postJson("/api/quality/revisions/{$revision->id}/submit", [
            'reviewer_id' => $reviewer->id,
        ])->assertOk();

        $this->actingAs($reviewer)
            ->postJson("/api/quality/revisions/{$revision->id}/review", [
                'action' => 'return',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->actingAs($reviewer)
            ->postJson("/api/quality/revisions/{$revision->id}/review", [
                'action' => 'return',
                'note' => 'Perlu perbaikan minor',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'draft',
        ]);

        $this->actingAs($creator)->postJson("/api/quality/revisions/{$revision->id}/lock")->assertOk();
        $this->actingAs($creator)->postJson("/api/quality/revisions/{$revision->id}/submit", [
            'reviewer_id' => $reviewer->id,
        ])->assertOk();

        $this->actingAs($reviewer)
            ->postJson("/api/quality/revisions/{$revision->id}/review", [
                'action' => 'pass',
                'approver_id' => $creator->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['approver_id']);

        $this->actingAs($reviewer)
            ->postJson("/api/quality/revisions/{$revision->id}/review", [
                'action' => 'pass',
                'approver_id' => $approver->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'in_approval',
            'disahkan_oleh' => $approver->id,
        ]);
    }

    public function test_save_content_requires_active_lock_owner_and_draft_status(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($other)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_html' => '<p>Konten tidak valid karena lock user lain</p>',
            ])
            ->assertForbidden();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_html' => '<h1>Konten Baru</h1><p>Dokumen siap review.</p>',
                'content_css' => '.doc{font-size:12px;}',
                'editor_json' => ['type' => 'doc', 'content' => []],
            ])
            ->assertOk();

        $expectedContent = \App\Support\QmhHtmlSanitizer::sanitize('<h1>Konten Baru</h1><p>Dokumen siap review.</p>');

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'content_html' => $expectedContent,
            'content_css' => '.doc{font-size:12px;}',
        ]);

        $revision->update(['status' => 'in_review']);

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_html' => '<p>Tidak boleh tersimpan</p>',
            ])
            ->assertStatus(422);
    }

    public function test_save_content_rejects_formulir_missing_required_answers(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevisionFormulir($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'answers_json' => [
                    'field_a' => '',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.field_a']);
    }

    public function test_save_content_allows_formulir_schema_snapshot_update_and_validates_against_snapshot(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevisionFormulir($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $overrideSchema = [
            'version' => 1,
            'doc_type' => 'fr',
            'questions' => [
                ['id' => 'field_b', 'label' => 'Kolom B', 'type' => 'text', 'required' => true],
            ],
        ];

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'form_schema_json' => $overrideSchema,
                'answers_json' => [
                    'field_b' => 'OK',
                ],
            ])
            ->assertOk();

        $revision->refresh();
        $this->assertEquals($overrideSchema, $revision->form_schema_json);

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'form_schema_json' => $overrideSchema,
                'answers_json' => [
                    'field_b' => '',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.field_b']);
    }

    public function test_save_content_rejects_non_formulir_when_only_answers_json_is_sent(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'answers_json' => [
                    'purpose' => 'Hanya jawaban tanpa konten',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content_html']);
    }

    public function test_save_content_rejects_non_formulir_when_content_html_is_blank_string(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_html' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content_html']);
    }

    public function test_save_content_rejects_non_formulir_schema_snapshot_updates(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_html' => '<p>Konten SOP</p>',
                'form_schema_json' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'x', 'label' => 'X', 'type' => 'text', 'required' => false],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['form_schema_json']);
    }

    private function createDraftRevision(User $creator): QmhDocumentRevision
    {
        $service = new QmhDocumentService;
        $document = $service->createDraft([
            'doc_code' => 'QMH-LOCK-'.str((string) now()->unix())->append((string) random_int(100, 999)),
            'title' => 'Draft untuk Locking',
            'clause' => 8,
            'doc_type' => 'sop',
        ], $creator->id);

        return $document->currentRevision;
    }

    private function createDraftRevisionFormulir(User $creator): QmhDocumentRevision
    {
        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Required',
            'clause' => 8,
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

        $service = new QmhDocumentService;
        $document = $service->createDraft([
            'doc_code' => 'QMH-FR-LOCK-'.str((string) now()->unix())->append((string) random_int(100, 999)),
            'title' => 'Draft Formulir',
            'clause' => 8,
            'doc_type' => 'fr',
            'template_id' => $template->id,
            'answers_json' => [],
        ], $creator->id);

        return $document->currentRevision;
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
