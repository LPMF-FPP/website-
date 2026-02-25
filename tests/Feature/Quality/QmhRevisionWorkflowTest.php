<?php

namespace Tests\Feature\Quality;

use App\Models\Permission;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\Quality\QmhDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QmhRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('quality.fr_v2.enabled', true);
        config()->set('quality.fr_v2.create_enabled', false);

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

    public function test_force_unlock_requires_special_permission_or_admin_role(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $operator */
        $operator = User::factory()->create(['role' => 'qmh_operator']);
        $revision = $this->createDraftRevision($owner);

        $createPermission = Permission::query()->firstWhere('name', 'qmh.create');
        $this->assertNotNull($createPermission);

        RolePermission::query()->updateOrCreate([
            'role' => 'qmh_operator',
            'permission_id' => $createPermission->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($operator)
            ->postJson("/api/quality/revisions/{$revision->id}/unlock", [
                'force' => true,
                'reason' => 'Take over',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('qmh_document_locks', [
            'revision_id' => $revision->id,
            'locked_by' => $owner->id,
            'force_unlocked_by' => null,
        ]);
    }

    public function test_force_unlock_allows_non_admin_user_with_force_unlock_permission(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $operator */
        $operator = User::factory()->create(['role' => 'qmh_operator']);
        $revision = $this->createDraftRevision($owner);

        $createPermission = Permission::query()->firstWhere('name', 'qmh.create');
        $this->assertNotNull($createPermission);
        $forceUnlockPermission = Permission::query()->firstWhere('name', 'qmh.unlock.force');
        $this->assertNotNull($forceUnlockPermission);

        RolePermission::query()->updateOrCreate([
            'role' => 'qmh_operator',
            'permission_id' => $createPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'qmh_operator',
            'permission_id' => $forceUnlockPermission->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($operator)
            ->postJson("/api/quality/revisions/{$revision->id}/unlock", [
                'force' => true,
                'reason' => 'Force unlock via delegated permission',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_locks', [
            'revision_id' => $revision->id,
            'force_unlocked_by' => $operator->id,
            'force_unlocked_reason' => 'Force unlock via delegated permission',
        ]);
    }

    public function test_force_unlock_allows_admin_even_without_explicit_force_unlock_permission(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($owner);

        $forceUnlockPermission = Permission::query()->firstWhere('name', 'qmh.unlock.force');
        $this->assertNotNull($forceUnlockPermission);

        RolePermission::query()->where([
            'role' => 'admin',
            'permission_id' => $forceUnlockPermission->id,
        ])->delete();

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/quality/revisions/{$revision->id}/unlock", [
                'force' => true,
                'reason' => 'Admin emergency override',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_locks', [
            'revision_id' => $revision->id,
            'force_unlocked_by' => $admin->id,
            'force_unlocked_reason' => 'Admin emergency override',
        ]);
    }

    public function test_force_unlock_allows_user_level_override_permission_without_role_mapping(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $operator */
        $operator = User::factory()->create(['role' => 'qmh_operator']);
        $revision = $this->createDraftRevision($owner);

        $createPermission = Permission::query()->firstWhere('name', 'qmh.create');
        $this->assertNotNull($createPermission);

        RolePermission::query()->updateOrCreate([
            'role' => 'qmh_operator',
            'permission_id' => $createPermission->id,
        ]);

        $operator->grantPermission('qmh.unlock.force');

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($operator)
            ->postJson("/api/quality/revisions/{$revision->id}/unlock", [
                'force' => true,
                'reason' => 'User-level override permission',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_locks', [
            'revision_id' => $revision->id,
            'force_unlocked_by' => $operator->id,
            'force_unlocked_reason' => 'User-level override permission',
        ]);
    }

    public function test_force_unlock_allows_legacy_permission_key_during_transition(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'admin']);
        /** @var User $operator */
        $operator = User::factory()->create(['role' => 'qmh_operator']);
        $revision = $this->createDraftRevision($owner);

        $createPermission = Permission::query()->firstWhere('name', 'qmh.create');
        $this->assertNotNull($createPermission);

        $legacyPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.force_unlock'],
            [
                'display_name' => 'Paksa Buka Kunci Dokumen Quality Management Hub (Legacy)',
                'module' => 'qmh',
                'action' => 'force-unlock',
            ]
        );

        RolePermission::query()->updateOrCreate([
            'role' => 'qmh_operator',
            'permission_id' => $createPermission->id,
        ]);

        RolePermission::query()->updateOrCreate([
            'role' => 'qmh_operator',
            'permission_id' => $legacyPermission->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($operator)
            ->postJson("/api/quality/revisions/{$revision->id}/unlock", [
                'force' => true,
                'reason' => 'Legacy key compatibility',
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_locks', [
            'revision_id' => $revision->id,
            'force_unlocked_by' => $operator->id,
            'force_unlocked_reason' => 'Legacy key compatibility',
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

        // Update signatories
        $revision->update(['diperiksa_oleh' => $reviewer->id, 'disahkan_oleh' => $approver->id]);

        $this->actingAs($reviewer)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

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

        $revision->refresh();
        $revision->load('lock');
        $this->assertNotNull($revision->lock);
        $this->assertFalse($revision->lock->isActive());
        $this->assertSame($reviewer->id, (int) $revision->lock->force_unlocked_by);

        $this->actingAs($creator)->postJson("/api/quality/revisions/{$revision->id}/lock")->assertOk();
        $this->actingAs($creator)->postJson("/api/quality/revisions/{$revision->id}/submit", [
            'reviewer_id' => $reviewer->id,
        ])->assertOk();

        // Ensure review state
        $revision->update(['status' => 'in_review', 'diperiksa_oleh' => $reviewer->id, 'disahkan_oleh' => $approver->id]);

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

    public function test_reject_endpoint_releases_active_lock_and_returns_revision_to_draft(): void
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

        $revision->update(['diperiksa_oleh' => $reviewer->id, 'disahkan_oleh' => $approver->id]);

        $this->actingAs($reviewer)
            ->postJson("/api/quality/revisions/{$revision->id}/review", [
                'action' => 'pass',
                'approver_id' => $approver->id,
            ])
            ->assertOk();

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($approver)
            ->postJson("/api/quality/revisions/{$revision->id}/reject", [
                'reason' => 'Perlu revisi mayor',
            ])
            ->assertOk();

        $revision->refresh();
        $revision->load('lock');

        $this->assertSame('draft', $revision->status);
        $this->assertNotNull($revision->lock);
        $this->assertFalse($revision->lock->isActive());
        $this->assertSame($approver->id, (int) $revision->lock->force_unlocked_by);
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
                'content_version' => $this->currentContentVersion($revision),
                'content_html' => '<p>Konten tidak valid karena lock user lain</p>',
            ])
            ->assertForbidden();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_version' => $this->currentContentVersion($revision),
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
                'content_version' => $this->currentContentVersion($revision),
                'content_html' => '<p>Tidak boleh tersimpan</p>',
            ])
            ->assertStatus(422);
    }

    public function test_save_content_allows_formulir_partial_answers_for_draft_editing(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevisionFormulir($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_version' => $this->currentContentVersion($revision),
                'answers_json' => [
                    'field_a' => '',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
        ]);

        $revision->refresh();
        $this->assertIsArray($revision->answers_json);
        $this->assertArrayHasKey('field_a', $revision->answers_json);
        $this->assertSame('', $revision->answers_json['field_a']);
        $this->assertSame('draft', $revision->status);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/submit", [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.field_a']);
    }

    public function test_save_content_returns_409_when_content_version_is_stale(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $staleVersion = $this->currentContentVersion($revision);
        $firstVersionHtml = '<p>Versi pertama</p>';

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_version' => $staleVersion,
                'content_html' => $firstVersionHtml,
            ])
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_version' => $staleVersion,
                'content_html' => '<p>Versi stale</p>',
            ])
            ->assertStatus(409)
            ->assertJsonPath('conflict.received_content_version', $staleVersion)
            ->assertJsonPath('conflict.current_content_version', $staleVersion + 1);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'content_html' => \App\Support\QmhHtmlSanitizer::sanitize($firstVersionHtml),
            'content_version' => $staleVersion + 1,
        ]);

        $this->assertDatabaseMissing('qmh_document_revisions', [
            'id' => $revision->id,
            'content_html' => \App\Support\QmhHtmlSanitizer::sanitize('<p>Versi stale</p>'),
        ]);
    }

    public function test_save_content_requires_content_version_field(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_html' => '<p>Tanpa content_version</p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content_version']);
    }

    public function test_save_content_rejects_content_version_below_one(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_version' => 0,
                'content_html' => '<p>Versi invalid</p>',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content_version']);
    }

    public function test_submit_rejects_formulir_when_required_answers_are_missing(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevisionFormulir($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $revision->refresh();
        $this->assertSame('draft', $revision->status);
        $this->assertNotNull($revision->lock);
        $this->assertTrue($revision->lock->isActive());
        $this->assertSame($creator->id, (int) $revision->lock->locked_by);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/submit", [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.field_a']);
    }

    public function test_submit_rejects_formulir_when_multiple_required_answers_are_missing(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevisionFormulirWithTwoRequiredFields($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $revision->refresh();
        $this->assertSame('draft', $revision->status);
        $this->assertNotNull($revision->lock);
        $this->assertTrue($revision->lock->isActive());
        $this->assertSame($creator->id, (int) $revision->lock->locked_by);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/submit", [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'answers_json.field_a',
                'answers_json.field_b',
            ]);
    }

    public function test_save_content_allows_formulir_schema_snapshot_update_and_validates_against_snapshot(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);

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
                'content_version' => $this->currentContentVersion($revision),
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
                'content_version' => $this->currentContentVersion($revision),
                'form_schema_json' => $overrideSchema,
                'answers_json' => [
                    'field_b' => '',
                ],
            ])
            ->assertOk();

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/submit", [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['answers_json.field_b']);
    }

    public function test_save_content_allows_non_formulir_answers_json_only(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevision($creator);

        $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/lock")
            ->assertOk();

        $this->actingAs($creator)
            ->putJson("/api/quality/revisions/{$revision->id}/content", [
                'content_version' => $this->currentContentVersion($revision),
                'answers_json' => [
                    'purpose' => 'Hanya jawaban tanpa konten',
                ],
            ])
            ->assertOk();
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
                'content_version' => $this->currentContentVersion($revision),
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
                'content_version' => $this->currentContentVersion($revision),
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

    public function test_close_legacy_and_duplicate_to_v2_supports_idempotent_retries(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $revision = $this->createDraftRevisionFormulir($creator);
        $revision->forceFill([
            'status' => 'published',
            'version_label' => 'E1-R1',
            'approved_at' => now(),
            'effective_date' => now()->toDateString(),
        ])->save();

        $idempotencyKey = 'cutover-fr-v2-001';

        $first = $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/close-legacy-and-duplicate-to-v2", [
                'idempotency_key' => $idempotencyKey,
                'reason' => 'Cutover FR-v2 tahap awal',
            ]);

        $first->assertOk();
        $first->assertJsonPath('data.idempotent_replay', false);
        $newDocumentId = (int) $first->json('data.new_document_id');
        $newRevisionId = (int) $first->json('data.new_revision_id');

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $revision->id,
            'status' => 'closed_legacy',
        ]);

        $this->assertDatabaseHas('qmh_document_revisions', [
            'id' => $newRevisionId,
            'document_id' => $newDocumentId,
            'status' => 'draft',
            'version_label' => 'E1-R0',
        ]);

        $second = $this->actingAs($creator)
            ->postJson("/api/quality/revisions/{$revision->id}/close-legacy-and-duplicate-to-v2", [
                'idempotency_key' => $idempotencyKey,
                'reason' => 'Cutover FR-v2 tahap awal',
            ]);

        $second->assertOk();
        $second->assertJsonPath('data.idempotent_replay', true);
        $second->assertJsonPath('data.new_document_id', $newDocumentId);
        $second->assertJsonPath('data.new_revision_id', $newRevisionId);

        $this->assertDatabaseHas('qmh_workflow_idempotency_keys', [
            'scope' => 'close_legacy_and_duplicate_to_v2:revision:'.$revision->id,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function createDraftRevision(User $creator): QmhDocumentRevision
    {
        $service = new QmhDocumentService;
        $document = $service->createDraft([
            'doc_code' => $this->makeDocCode('QMH-LOCK'),
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
            'shell_mode' => 'full',
            'orientation_policy' => 'portrait',
            'show_signoff_footer' => true,
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
            'doc_code' => $this->makeDocCode('QMH-FR-LOCK'),
            'title' => 'Draft Formulir',
            'clause' => 8,
            'doc_type' => 'fr',
            'template_id' => $template->id,
            'answers_json' => [],
        ], $creator->id);

        return $document->currentRevision;
    }

    private function createDraftRevisionFormulirWithTwoRequiredFields(User $creator): QmhDocumentRevision
    {
        $template = QmhTemplate::query()->create([
            'name' => 'Template FR Required 2 Fields',
            'clause' => 8,
            'doc_type' => 'fr',
            'shell_mode' => 'full',
            'orientation_policy' => 'portrait',
            'show_signoff_footer' => true,
            'version' => 1,
            'storage_disk' => 'local',
            'source_docx_path' => 'qmh/templates/fr/required-two-fields.docx',
            'is_active' => true,
            'metadata' => [
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => 'fr',
                    'questions' => [
                        ['id' => 'field_a', 'label' => 'Kolom A', 'type' => 'text', 'required' => true],
                        ['id' => 'field_b', 'label' => 'Kolom B', 'type' => 'textarea', 'required' => true],
                    ],
                ],
            ],
        ]);

        $service = new QmhDocumentService;
        $document = $service->createDraft([
            'doc_code' => $this->makeDocCode('QMH-FR-LOCK-2REQ'),
            'title' => 'Draft Formulir Two Required',
            'clause' => 8,
            'doc_type' => 'fr',
            'template_id' => $template->id,
            'answers_json' => [],
        ], $creator->id);

        return $document->currentRevision;
    }

    private function currentContentVersion(QmhDocumentRevision $revision): int
    {
        $revision->refresh();

        return max(1, (int) $revision->content_version);
    }

    private function makeDocCode(string $prefix): string
    {
        return $prefix.'-'.(string) Str::uuid();
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

        $forceUnlockPermission = Permission::query()->updateOrCreate(
            ['name' => 'qmh.unlock.force'],
            [
                'display_name' => 'Paksa Buka Kunci Dokumen Quality Management Hub',
                'module' => 'qmh',
                'action' => 'unlock-force',
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

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $forceUnlockPermission->id,
        ]);
    }
}
