<?php

namespace Tests\Unit\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\User;
use App\Services\Quality\QmhRevisionApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhRevisionApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_predict_next_approval_version_uses_latest_published_revision_as_base(): void
    {
        $published = new QmhDocumentRevision([
            'edition_number' => 1,
            'revision_number' => 1,
            'version_label' => 'E1-R1',
            'status' => 'published',
        ]);

        $draft = new QmhDocumentRevision([
            'edition_number' => 1,
            'revision_number' => 1,
            'version_label' => 'E1-R1',
            'status' => 'draft',
        ]);

        $next = $draft->predictNextApprovalVersion(false, $published);

        $this->assertSame(1, $next['edition_number']);
        $this->assertSame(2, $next['revision_number']);
        $this->assertSame('E1-R2', $next['version_label']);
        $this->assertSame('auto', $next['version_bump_mode']);
    }

    public function test_auto_bump_promotes_new_edition_when_previous_revision_is_nine(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);

        [$revision] = $this->seedInApprovalRevision(
            $creator,
            $reviewer,
            $approver,
            1,
            9
        );

        $service = new QmhRevisionApprovalService;
        $updated = $service->approve($revision, $approver->id, false, null);

        $this->assertSame(2, $updated->edition_number);
        $this->assertSame(0, $updated->revision_number);
        $this->assertSame('E2-R0', $updated->version_label);
    }

    public function test_auto_bump_increments_minor_revision_when_boundary_not_reached(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'admin']);

        [$revision] = $this->seedInApprovalRevision(
            $creator,
            $reviewer,
            $approver,
            3,
            4
        );

        $service = new QmhRevisionApprovalService;
        $updated = $service->approve($revision, $approver->id, false, null);

        $this->assertSame(3, $updated->edition_number);
        $this->assertSame(5, $updated->revision_number);
        $this->assertSame('E3-R5', $updated->version_label);
    }

    /**
     * @return array{0: QmhDocumentRevision, 1: QmhDocumentRevision}
     */
    private function seedInApprovalRevision(User $creator, User $reviewer, User $approver, int $edition, int $revision): array
    {
        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-UNIT-'.str((string) now()->unix())->append((string) random_int(100, 999)),
            'title' => 'Unit Test Versioning',
            'clause' => 8,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $published = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => $edition,
            'revision_number' => $revision,
            'version_label' => sprintf('E%d-R%d', $edition, $revision),
            'status' => 'published',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'approved_at' => now()->subDay(),
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $inApproval = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'in_approval',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $creator->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
        ]);

        $document->current_revision_id = $inApproval->id;
        $document->save();

        return [$inApproval, $published];
    }
}
