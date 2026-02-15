<?php

namespace Tests\Feature\Requests;

use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\Sequence;
use App\Models\SystemSetting;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingRepairService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestDeleteCompactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('documents');

        // Seed settings for numbering
        $this->setSetting('numbering.ba.reset', 'monthly');
        $this->setSetting('numbering.ba.pattern', 'BA/{SEQ:3}/{MM}/{YYYY}');
        $this->setSetting('numbering.ba.start_from', 1);

        $this->setSetting('numbering.tracking.reset', 'monthly');
        $this->setSetting('numbering.tracking.pattern', 'LPMF{SEQ:3}{MM}{YYYY}');
        $this->setSetting('numbering.tracking.start_from', 1);

        // Setup initial sequences (ensure clean slate)
        Sequence::where('scope', 'ba')->delete();
        Sequence::where('scope', 'tracking')->delete();
    }

    private function setSetting($key, $value)
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => 'string', 'group' => 'numbering']
        );
        // Clear cache if settings uses it
        \Illuminate\Support\Facades\Cache::forget('settings.all');
    }

    public function test_it_compacts_ba_and_tracking_numbers_after_deletion()
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::create(2026, 2, 10, 10, 0, 0);
        CarbonImmutable::setTestNow($date);

        // Create 3 requests (force numbering service generation by setting numbers to null)
        $req1 = TestRequest::factory()->create(['created_at' => $date, 'request_number' => null, 'receipt_number' => null]);
        $req2 = TestRequest::factory()->create(['created_at' => $date->addMinute(), 'request_number' => null, 'receipt_number' => null]);
        $req3 = TestRequest::factory()->create(['created_at' => $date->addMinutes(2), 'request_number' => null, 'receipt_number' => null]);

        $this->assertStringContainsString('BA/001', $req1->request_number);
        $this->assertStringContainsString('BA/002', $req2->request_number);
        $this->assertStringContainsString('BA/003', $req3->request_number);

        // Delete middle request (req2)
        $req2->delete();

        // Run compaction manually
        app(NumberingRepairService::class)->compactRequestNumbersForBucket($date);

        $req1->refresh();
        $req3->refresh();

        // Req1 should be unchanged
        $this->assertStringContainsString('BA/001', $req1->request_number);

        // Req3 should shift down to 002
        $this->assertStringContainsString('BA/002', $req3->request_number);

        // Counter should be at 2
        $counter = Sequence::where('scope', 'ba')->where('bucket', '2026-02')->value('current_value');
        $this->assertEquals(2, $counter);
    }

    public function test_it_skips_locked_requests_during_compaction()
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::create(2026, 2, 10, 10, 0, 0);
        CarbonImmutable::setTestNow($date);

        $req1 = TestRequest::factory()->create(['created_at' => $date, 'request_number' => null, 'receipt_number' => null]); // 001
        $req2 = TestRequest::factory()->create(['created_at' => $date->addMinute(), 'request_number' => null, 'receipt_number' => null]); // 002
        $req3 = TestRequest::factory()->create(['created_at' => $date->addMinutes(2), 'request_number' => null, 'receipt_number' => null]); // 003
        $req4 = TestRequest::factory()->create(['created_at' => $date->addMinutes(3), 'request_number' => null, 'receipt_number' => null]); // 004

        // Lock req4 by adding a test process
        $sample = Sample::factory()->create(['test_request_id' => $req4->id]);
        SampleTestProcess::factory()->create(['sample_id' => $sample->id]);

        // Delete req2
        $req2->delete();

        // Compact
        app(NumberingRepairService::class)->compactRequestNumbersForBucket($date);

        $req1->refresh();
        $req3->refresh();
        $req4->refresh();

        // 001 -> 001 (unchanged)
        $this->assertStringContainsString('BA/001', $req1->request_number);

        // 003 -> 002 (compacted)
        $this->assertStringContainsString('BA/002', $req3->request_number);

        // 004 -> 004 (LOCKED, skipped)
        $this->assertStringContainsString('BA/004', $req4->request_number);

        // Counter should be synced to max (4)
        $counter = Sequence::where('scope', 'ba')->where('bucket', '2026-02')->value('current_value');
        $this->assertEquals(4, $counter);
    }

    public function test_it_cascades_changes_to_evidence_units_and_documents()
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::create(2026, 2, 10, 10, 0, 0);
        CarbonImmutable::setTestNow($date);

        $req1 = TestRequest::factory()->create(['created_at' => $date, 'request_number' => null, 'receipt_number' => null]); // 001
        $req2 = TestRequest::factory()->create(['created_at' => $date->addMinute(), 'request_number' => null, 'receipt_number' => null]); // 002

        // Setup req2 with evidence unit and document
        $sample = Sample::factory()->create(['test_request_id' => $req2->id]);
        $eu = EvidenceUnit::create([
            'request_id' => $req2->id,
            'receipt_code' => $req2->receipt_number,
            'sample_id' => $sample->id,
            'sample_code' => $sample->sample_code,
            'unit_type' => 'sample',
            'amount' => 1,
            'unit' => 'pcs',
            'storage_location' => 'Rak 1',
        ]);

        // Create document with path containing request number
        $oldRequestNumber = $req2->request_number;
        $inv = $req2->investigator;
        $docPath = "investigators/{$inv->folder_key}/{$req2->request_number}/uploads/test.pdf";
        $doc = Document::factory()->create([
            'test_request_id' => $req2->id,
            'file_path' => $docPath,
            'path' => $docPath,
        ]);

        // Create req3 (003), delete req1 (001) to force shift down
        $req3 = TestRequest::factory()->create(['created_at' => $date->addMinutes(2), 'request_number' => null, 'receipt_number' => null]); // 003
        $req1->delete();

        // Compact: req2 (002) should become 001
        app(NumberingRepairService::class)->compactRequestNumbersForBucket($date);

        $req2->refresh();
        $eu->refresh();
        $doc->refresh();

        // Req2 -> 001
        $this->assertStringContainsString('BA/001', $req2->request_number);

        // Evidence Unit should match new receipt number
        $this->assertEquals($req2->receipt_number, $eu->receipt_code);

        // Document path should be updated
        $this->assertStringContainsString($req2->request_number, $doc->file_path);
        $this->assertStringNotContainsString($oldRequestNumber, $doc->file_path);
    }

    public function test_it_handles_auto_compaction_via_controller()
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::create(2026, 2, 10, 10, 0, 0);
        CarbonImmutable::setTestNow($date);

        $req1 = TestRequest::factory()->create(['created_at' => $date, 'request_number' => null, 'receipt_number' => null]);
        $req2 = TestRequest::factory()->create(['created_at' => $date->addMinute(), 'request_number' => null, 'receipt_number' => null]);
        $req3 = TestRequest::factory()->create(['created_at' => $date->addMinutes(2), 'request_number' => null, 'receipt_number' => null]);

        // Delete req2 via controller
        $this->actingAs($user)->delete(route('requests.destroy', $req2->id));

        $req3->refresh();

        // Req3 should be compacted to 002 automatically
        $this->assertStringContainsString('BA/002', $req3->request_number);
    }

    public function test_it_invalidates_cache_for_old_numbers_during_compaction()
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::create(2026, 2, 10, 10, 0, 0);
        CarbonImmutable::setTestNow($date);

        // Create 3 requests: 001, 002, 003
        $req1 = TestRequest::factory()->create(['created_at' => $date, 'request_number' => null, 'receipt_number' => null]);
        $req2 = TestRequest::factory()->create(['created_at' => $date->addMinute(), 'request_number' => null, 'receipt_number' => null]);
        $req3 = TestRequest::factory()->create(['created_at' => $date->addMinutes(2), 'request_number' => null, 'receipt_number' => null]);

        // Manually trigger numbering service to ensure numbers are generated properly
        // Actually, factory should trigger observers if they are set up. But RequestDeleteCompactionTest sets up settings in setUp().
        // Let's assume numbers are generated.

        $this->assertStringContainsString('BA/001', $req1->request_number);
        $this->assertStringContainsString('BA/002', $req2->request_number);
        $this->assertStringContainsString('BA/003', $req3->request_number);

        $ba3 = $req3->request_number; // BA/003 (old number)

        // Seed cache for old number
        \Illuminate\Support\Facades\Cache::put('track:condensed:'.$ba3, 'cached_old_number');

        // Delete req2 (BA/002)
        $req2->delete();

        // Run compaction: BA/003 should become BA/002
        app(NumberingRepairService::class)->compactRequestNumbersForBucket($date);

        $req3->refresh();
        $this->assertStringContainsString('BA/002', $req3->request_number);

        // Verify cache for OLD BA/003 is gone
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('track:condensed:'.$ba3), "Cache for old number $ba3 should be cleared");
    }
}
