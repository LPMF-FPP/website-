<?php

namespace Tests\Unit\Services\Quality;

use App\Models\User;
use App\Services\Quality\QmhDocumentService;
use App\Services\Quality\QmhRevisionLockService;
use App\Services\Quality\QmhRevisionTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QmhRevisionTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_rejects_when_reviewer_is_same_as_creator(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);

        $documentService = new QmhDocumentService;
        $document = $documentService->createDraft([
            'doc_code' => 'QMH-TS-001',
            'title' => 'Transition Rule Test',
            'clause' => 7,
            'doc_type' => 'ik',
        ], $creator->id);

        $service = new QmhRevisionTransitionService;

        $this->expectException(ValidationException::class);

        $service->submitForReview($document->currentRevision, $creator->id, $creator->id);
    }

    public function test_pass_review_rejects_when_approver_equals_reviewer(): void
    {
        /** @var User $creator */
        $creator = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'admin']);

        $documentService = new QmhDocumentService;
        $document = $documentService->createDraft([
            'doc_code' => 'QMH-TS-002',
            'title' => 'Transition Rule Test 2',
            'clause' => 7,
            'doc_type' => 'ik',
        ], $creator->id);

        $lockService = new QmhRevisionLockService;
        $lockService->acquire($document->currentRevision, $creator->id);

        $transitionService = new QmhRevisionTransitionService;
        $transitionService->submitForReview($document->currentRevision, $creator->id, $reviewer->id);

        $this->expectException(ValidationException::class);

        $transitionService->passReview($document->currentRevision->fresh(), $reviewer->id, $reviewer->id);
    }
}
