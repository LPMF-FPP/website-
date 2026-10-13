<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryStockCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

class StockCardPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_pdf_uses_complete_canonical_screen_report_with_full_notes_and_precision(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false, 'name' => 'Full Location Identity For Inventory Reconciliation']);
        $notes = 'Complete note longer than twenty-five characters retained on screen and in PDF.';
        for ($i = 0; $i < 51; $i++) {
            InventoryMovement::factory()->create(['item_id' => $item->id, 'movement_type' => 'RECEIPT', 'to_location_id' => $location->id, 'qty' => '0.001', 'notes' => $notes, 'performed_at' => '2026-10-01 12:00:00']);
        }
        $filters = ['item_id' => $item->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'page' => 2];
        $screen = $this->actingAs($viewer)->get(route('inventory.stock-card', $filters));
        $screen->assertOk();
        $expected = $screen->viewData('report');
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('A4', 'landscape')->once()->andReturnSelf();
        $pdf->shouldReceive('stream')->once()->andReturn(new Response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($expected, $notes, $location): bool {
            $this->assertSame('inventory.pdf.stock-card', $view);
            $this->assertCount(51, $data['stockCard']);
            foreach (['opening_balance', 'closing_balance', 'total_in', 'total_out', 'filters', 'timezone'] as $key) {
                $this->assertSame($expected[$key], $data['report'][$key]);
            }
            $html = view($view, $data)->render();
            $this->assertStringContainsString($notes, $html);
            $this->assertStringContainsString($location->name, $html);
            $this->assertStringContainsString('0,051', $html);

            return true;
        })->andReturn($pdf);
        $this->get(route('inventory.stock-card.print', $filters))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_empty_period_pdf_is_generated_with_nonzero_ledger_opening_and_closing(): void
    {
        $viewer = User::factory()->create();
        $viewer->grantPermission('inventori.view');
        $item = InventoryItem::factory()->create();
        $location = InventoryLocation::factory()->create(['is_restricted' => false]);
        InventoryMovement::factory()->create(['item_id' => $item->id, 'movement_type' => 'RECEIPT', 'to_location_id' => $location->id, 'qty' => '3.123', 'performed_at' => '2026-09-01 12:00:00']);
        $filters = ['item_id' => $item->id, 'date_from' => '2026-09-05', 'date_to' => '2026-09-06'];
        $report = app(InventoryStockCardService::class)->report($filters, $viewer);
        $html = view('inventory.pdf.stock-card', ['report' => $report, 'stockCard' => $report['rows'], 'item' => $item, 'lot' => null, 'location' => null, 'filters' => $filters, 'generatedBy' => $viewer])->render();
        $this->assertSame('3.123', $report['opening_balance']);
        $this->assertSame('3.123', $report['closing_balance']);
        $this->assertSame(2, substr_count($html, '3,123'));
        $this->actingAs($viewer)->get(route('inventory.stock-card.print', $filters))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
