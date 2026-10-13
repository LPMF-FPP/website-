<?php

declare(strict_types=1);

namespace Tests\Browser\Inventory;

use App\Enums\SampleDisposalStatus;
use App\Models\Sample;
use App\Models\SampleDisposal;
use App\Models\SampleTestProcess;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Dusk\Browser;
use Tests\Concerns\RecreatesApprovedBrowserDatabase;
use Tests\DuskTestCase;

class SampleDisposalWorkflowTest extends DuskTestCase
{
    use RecreatesApprovedBrowserDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recreateApprovedBrowserDatabase();
        $this->seed(PermissionSeeder::class);
    }

    public function test_operator_can_review_eligible_manifest_validate_approver_and_execute_without_photos(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $this->browse(function (Browser $browser) use ($actor, $sample): void {
            $browser->loginAs($actor)->visit(route('inventory.disposal.index'))->waitForText('Pemusnahan Sampel')
                ->assertSee('91 hari')->check('input[aria-label="Pilih '.$sample->sample_code.'"]')
                ->click('form[action="'.route('inventory.disposal.create').'"] button[type="submit"]')
                ->waitForText('1. Manifest')->assertSee('BROWSER-LHU')->assertSee($sample->sample_code)
                ->assertDisabled('#execute-sample-disposal');
            $this->fillEvidence($browser);
            $browser->assertSelected('method', 'bakar')->assertInputValue('approver_role', 'Kepala Lab');
            $browser->type('approver_name', '');
            $browser->check('[name="review_confirmed"]');
            $browser->press('Eksekusi Pemusnahan')->assertFocused('#approver_name')
                ->assertInputValue('approver_role', 'Kepala Lab');
            $browser->script('HTMLFormElement.prototype.submit.call(document.querySelector("#sample-disposal-form"));');
            $browser->waitForText('Nama approver wajib diisi.')->assertFocused('[role="alert"][tabindex="-1"]')
                ->assertInputValue('approver_role', 'Kepala Lab')->assertNotChecked('[name="review_confirmed"]');
            $browser->type('approver_name', 'Approver Browser')->check('[name="review_confirmed"]')
                ->press('Eksekusi Pemusnahan')->waitForText('berhasil dieksekusi.')
                ->assertSee('Batch selesai: 1 sampel')->assertSee('Approver Browser')->assertSee('Saksi Browser')
                ->assertSee('Tidak ada foto dokumentasi');
            $sample->testProcesses()->update(['metadata' => ['lhu_number' => 'CHANGED-AFTER-EXECUTION']]);
            $browser->refresh()->waitForText('Batch selesai')->assertSee('BROWSER-LHU')->assertDontSee('CHANGED-AFTER-EXECUTION');
        });
        $this->assertSame(SampleDisposalStatus::DISPOSED, $sample->fresh()->disposal_status);
        $this->assertSame(1, SampleDisposal::count());
        $this->assertSame(0, \App\Models\InventoryMovement::count());
    }

    public function test_changed_manifest_rejects_execution_and_preserves_correctable_evidence(): void
    {
        $actor = $this->operator();
        $sample = $this->eligible();
        $this->browse(function (Browser $browser) use ($actor, $sample): void {
            $browser->loginAs($actor)->visit(route('inventory.disposal.create', ['sample_ids' => [$sample->id]]))
                ->waitForText('1. Manifest');
            $this->fillEvidence($browser);
            $sample->testProcesses()->update(['metadata' => ['lhu_number' => 'REVISED-BEFORE-EXECUTION']]);
            $browser->check('[name="review_confirmed"]')->press('Eksekusi Pemusnahan')
                ->waitForText('Daftar sampel berubah')->assertSee('REVISED-BEFORE-EXECUTION')
                ->assertInputValue('approver_name', 'Approver Browser')->assertNotChecked('[name="review_confirmed"]');
            $browser->script('window.__setTheme("dark");');
            $browser->assertSee('Segarkan pemilihan batch');
        });
        $this->assertNull($sample->fresh()->disposal_id);
        $this->assertSame(0, SampleDisposal::count());
    }

    public function test_sample_view_only_account_sees_eligible_data_and_no_execution_controls(): void
    {
        $actor = $this->operator(false);
        $sample = $this->eligible();
        $this->browse(function (Browser $browser) use ($actor, $sample): void {
            $browser->loginAs($actor)->visit(route('inventory.disposal.index'))->waitForText('Pemusnahan Sampel')
                ->assertSee($sample->sample_code)->assertSee('Lihat saja')->assertDontSee('Tinjau semua')
                ->assertMissing('input[type="checkbox"]');
        });
    }

    public function test_selection_survives_pagination_tabs_and_preview_cancellation(): void
    {
        $actor = $this->operator();
        $samples = [];
        for ($index = 1; $index <= 21; $index++) {
            $samples[] = $this->eligible('BROWSER-SELECTION-'.$index);
        }
        $first = $samples[0];
        $last = $samples[20];
        $this->browse(function (Browser $browser) use ($actor, $first, $last): void {
            $browser->loginAs($actor)->visit(route('inventory.disposal.index'))->waitForText('21 sampel memenuhi syarat pemusnahan')
                ->check('input[aria-label="Pilih '.$first->sample_code.'"]')
                ->click('a[aria-label][href*="eligible_page=2"]')->waitForText($last->sample_code)
                ->check('input[aria-label="Pilih '.$last->sample_code.'"]')
                ->clickLink('Riwayat pemusnahan')->waitForText('Belum ada riwayat')
                ->clickLink('Sampel siap ditinjau')->waitForText($first->sample_code)
                ->assertChecked('input[aria-label="Pilih '.$first->sample_code.'"]')
                ->click('form[action="'.route('inventory.disposal.create').'"] button[type="submit"]')
                ->waitForText('1. Manifest 2 sampel')->assertSee($first->sample_code)->assertSee($last->sample_code)
                ->assertDontSee('BROWSER-SELECTION-2 ·')
                ->clickLink('Batal dan pilih ulang')->waitForText('21 sampel memenuhi syarat pemusnahan')
                ->assertChecked('input[aria-label="Pilih '.$first->sample_code.'"]')
                ->click('form[action="'.route('inventory.disposal.create').'"] button[type="submit"]')
                ->waitForText('1. Manifest 2 sampel')->assertSee($last->sample_code);
        });
        $this->assertSame(0, SampleDisposal::count());
    }

    public function test_evidence_edits_and_witness_changes_revoke_confirmation_and_restored_forms_recover(): void
    {
        $actor = $this->operator();
        $witness = User::factory()->create(['name' => 'Saksi Akun', 'title_prefix' => 'dr.', 'title_suffix' => 'M.Si', 'rank' => 'Pengawas']);
        $sample = $this->eligible();
        $this->browse(function (Browser $browser) use ($actor, $witness, $sample): void {
            $browser->loginAs($actor)->visit(route('inventory.disposal.create', ['sample_ids' => [$sample->id]]))->waitForText('1. Manifest');
            $this->fillEvidence($browser);
            $browser->check('[name="review_confirmed"]')->type('notes', 'Bukti baru')
                ->assertNotChecked('[name="review_confirmed"]')->assertDisabled('#execute-sample-disposal')
                ->check('[name="review_confirmed"]')->press('Tambah saksi')
                ->assertNotChecked('[name="review_confirmed"]')->assertDisabled('#execute-sample-disposal')
                ->type('witnesses[0][identity]', 'IDENTITAS-SEBELUMNYA')
                ->select('witnesses[0][user_id]', (string) $witness->id)
                ->assertInputValue('witnesses[0][name]', 'dr. Saksi Akun M.Si')
                ->assertInputValue('witnesses[0][identity]', '')
                ->select('witnesses[0][user_id]', '')->assertInputValue('witnesses[0][name]', '')
                ->assertInputValue('witnesses[0][role]', '');
            $browser->script('const form = document.querySelector("#sample-disposal-form"); const state = window.Alpine.$data(form); state.pending = true; state.confirmed = true; window.dispatchEvent(new PageTransitionEvent("pageshow", { persisted: true }));');
            $browser->assertNotChecked('[name="review_confirmed"]')->assertDisabled('#execute-sample-disposal')
                ->check('[name="review_confirmed"]')->assertEnabled('#execute-sample-disposal');
        });
        $this->assertSame(0, SampleDisposal::count());
    }

    private function fillEvidence(Browser $browser): void
    {
        $browser->select('method', 'bakar')->waitFor('[name="witnesses[0][name]"]')
            ->type('witnesses[0][name]', 'Saksi Browser')->type('witnesses[0][role]', 'Pengawas')
            ->type('approver_name', 'Approver Browser')->type('approver_role', 'Kepala Lab');
    }

    private function operator(bool $execute = true): User
    {
        $user = User::factory()->create(['rank' => 'Pengawas']);
        $user->grantPermission('sample-disposal.view');
        if ($execute) {
            $user->grantPermission('sample-disposal.execute');
        }

        return $user;
    }

    private function eligible(string $code = 'BROWSER-SAMPLE-DISPOSAL'): Sample
    {
        $sample = Sample::factory()->create(['sample_code' => $code, 'disposal_status' => SampleDisposalStatus::PENDING, 'disposal_id' => null, 'disposed_at' => null]);
        SampleTestProcess::factory()->create(['sample_id' => $sample->id, 'stage' => 'interpretation', 'completed_at' => now()->subDays(91), 'metadata' => ['lhu_number' => 'BROWSER-LHU']]);

        return $sample;
    }
}
