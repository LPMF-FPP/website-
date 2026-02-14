<?php

namespace Tests\Unit\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Services\Quality\QmhDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QmhDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_correct_stats_per_clause(): void
    {
        // Clause 4: Healthy (1 Published)
        $doc4 = QmhDocument::factory()->create(['clause' => 4]);
        $revision4 = QmhDocumentRevision::factory()->create([
            'document_id' => $doc4->id,
            'status' => 'published',
        ]);
        $doc4->update(['current_revision_id' => $revision4->id]);

        // Clause 5: Active (2 Draft)
        $doc5 = QmhDocument::factory()->create(['clause' => 5]);
        $revision5 = QmhDocumentRevision::factory()->create([
            'document_id' => $doc5->id,
            'status' => 'draft',
            'created_at' => now(),
        ]);
        $doc5->update(['current_revision_id' => $revision5->id]);

        $doc5b = QmhDocument::factory()->create(['clause' => 5]);
        $revision5b = QmhDocumentRevision::factory()->create([
            'document_id' => $doc5b->id,
            'status' => 'draft',
            'created_at' => now(),
        ]);
        $doc5b->update(['current_revision_id' => $revision5b->id]);

        // Clause 6: Warning (1 Overdue Draft)
        $doc6 = QmhDocument::factory()->create(['clause' => 6]);
        $revision6 = QmhDocumentRevision::factory()->create([
            'document_id' => $doc6->id,
            'status' => 'draft',
            'created_at' => now()->subDays(40), // > 30 days overdue
        ]);
        $doc6->update(['current_revision_id' => $revision6->id]);

        $service = new QmhDashboardService;
        $stats = $service->getPulseStats();

        // Check Clause 4
        $this->assertEquals(1, $stats['clauses'][4]['published']);
        $this->assertEquals('healthy', $stats['clauses'][4]['health']);

        // Check Clause 5
        $this->assertEquals(2, $stats['clauses'][5]['draft']);
        // Note: 'active' logic requires >5 drafts or >2 reviews, so 2 drafts is still 'healthy' unless logic changed.
        // Let's re-read the logic: elseif ($s['review'] > 2 || $s['draft'] > 5) -> active.
        // So 2 drafts = healthy.
        $this->assertEquals('healthy', $stats['clauses'][5]['health']);

        // Check Clause 6
        $this->assertEquals(1, $stats['clauses'][6]['overdue']);
        $this->assertEquals('warning', $stats['clauses'][6]['health']);

        // Global Pulse
        $this->assertEquals('warning', $stats['global_pulse']); // Because of Clause 6 overdue
    }
}
