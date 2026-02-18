<?php

namespace Tests\Browser\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhTemplate;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class QmhCreateAndEditWorkflowTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_user_can_create_sop_draft_from_stepper_flow(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'manager']);

        $template = $this->createTemplate(4, 'sop', 'Template SOP Dusk Workflow');

        $docCode = 'QMH-SOP-DUSK-'.strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $title = 'SOP Dusk Stepper Flow';

        $this->browse(function (Browser $browser) use ($actor, $reviewer, $approver, $template, $docCode, $title) {
            $browser->loginAs($actor)
                ->visit('/quality/documents/create')
                ->waitForText('Buat Dokumen QMH');

            $browser->script('window.localStorage.clear();');

            $browser->refresh()
                ->waitForText('Buat Dokumen QMH')
                ->select('doc_type', 'sop')
                ->press('Lanjut')
                ->type('doc_code', $docCode)
                ->type('title', $title)
                ->press('Lanjut')
                ->waitUntil("return (() => { const el = document.querySelector('#template_id'); return !!el && el.options.length > 0; })();", 15)
                ->select('#template_id', (string) $template->id)
                ->waitUntil("return !!document.querySelector('#diperiksa_oleh option[value=\"{$reviewer->id}\"]') && !!document.querySelector('#disahkan_oleh option[value=\"{$approver->id}\"]');", 15);

            $browser->script("(() => {
                const dibuat = document.querySelector('#dibuat_oleh');
                const diperiksa = document.querySelector('#diperiksa_oleh');
                const disahkan = document.querySelector('#disahkan_oleh');
                if (dibuat) {
                    dibuat.value = '{$actor->id}';
                    dibuat.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (diperiksa) {
                    diperiksa.value = '{$reviewer->id}';
                    diperiksa.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (disahkan) {
                    disahkan.value = '{$approver->id}';
                    disahkan.dispatchEvent(new Event('change', { bubbles: true }));
                }
            })();");

            $browser->waitUntil("return Number(document.querySelector('#diperiksa_oleh')?.value || 0) === {$reviewer->id} && Number(document.querySelector('#disahkan_oleh')?.value || 0) === {$approver->id};", 10)
                ->press('Review')
                ->waitForText('Review & Simpan')
                ->press('Simpan Draft')
                ->waitForText('Preview Dokumen Sebelum Simpan Draft')
                ->press('Lanjut Simpan Draft')
                ->waitForText('Editor Dokumen QMH', 15)
                ->assertSee($docCode);
        });

        $createdDocument = QmhDocument::query()
            ->where('doc_code', $docCode)
            ->first();

        $this->assertNotNull($createdDocument);
        $this->assertNotNull($createdDocument?->current_revision_id);
    }

    public function test_user_can_create_fr_v2_draft_with_source_pdf(): void
    {
        config()->set('quality.fr_v2.enabled', true);
        config()->set('quality.fr_v2.create_enabled', true);

        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'manager']);

        $parentSop = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-PARENT-DUSK-001',
            'title' => 'SOP Induk FR-v2 Dusk',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $docCode = 'QMH-FR-DUSK-'.strtoupper(substr(md5((string) microtime(true)), 0, 8));
        $title = 'Form FR-v2 Dusk Flow';
        $sourcePdfPath = $this->createPdfFixture('fr-v2-source-'.$docCode.'.pdf');

        $this->browse(function (Browser $browser) use ($actor, $reviewer, $approver, $parentSop, $docCode, $title, $sourcePdfPath) {
            $browser->loginAs($actor)
                ->visit('/quality/documents/create')
                ->waitForText('Buat Dokumen QMH');

            $browser->script('window.localStorage.clear();');

            $browser->refresh()
                ->waitForText('Buat Dokumen QMH')
                ->select('doc_type', 'fr')
                ->press('Lanjut')
                ->type('doc_code', $docCode)
                ->type('title', $title)
                ->select('parent_sop_id', (string) $parentSop->id)
                ->press('Lanjut')
                ->attach('source_pdf_file', $sourcePdfPath)
                ->waitUntil("return !!document.querySelector('#diperiksa_oleh option[value=\"{$reviewer->id}\"]') && !!document.querySelector('#disahkan_oleh option[value=\"{$approver->id}\"]');", 15);

            $browser->script("(() => {
                const dibuat = document.querySelector('#dibuat_oleh');
                const diperiksa = document.querySelector('#diperiksa_oleh');
                const disahkan = document.querySelector('#disahkan_oleh');
                if (dibuat) {
                    dibuat.value = '{$actor->id}';
                    dibuat.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (diperiksa) {
                    diperiksa.value = '{$reviewer->id}';
                    diperiksa.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (disahkan) {
                    disahkan.value = '{$approver->id}';
                    disahkan.dispatchEvent(new Event('change', { bubbles: true }));
                }
            })();");

            $browser->waitUntil("return Number(document.querySelector('#diperiksa_oleh')?.value || 0) === {$reviewer->id} && Number(document.querySelector('#disahkan_oleh')?.value || 0) === {$approver->id};", 10)
                ->press('Review')
                ->waitForText('Review & Simpan')
                ->press('Simpan Draft')
                ->waitForText('Preview Dokumen Sebelum Simpan Draft')
                ->press('Lanjut Simpan Draft')
                ->waitForText('Editor Dokumen QMH', 15)
                ->assertSee($docCode);
        });

        $createdDocument = QmhDocument::query()
            ->where('doc_code', $docCode)
            ->firstOrFail();

        $revision = QmhDocumentRevision::query()
            ->where('document_id', $createdDocument->id)
            ->firstOrFail();

        $this->assertNotNull($revision->source_pdf_path);
        $this->assertNotNull($revision->source_pdf_sha256);
    }

    public function test_edit_page_can_save_preview_and_submit_for_review(): void
    {
        /** @var User $actor */
        $actor = User::factory()->create(['role' => 'admin']);
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        /** @var User $approver */
        $approver = User::factory()->create(['role' => 'manager']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-EDIT-DUSK-001',
            'title' => 'SOP Edit Workflow Dusk',
            'clause' => 4,
            'doc_type' => 'sop',
            'owner_label' => 'Laboratorium',
            'is_active' => true,
        ]);

        $revision = QmhDocumentRevision::query()->create([
            'document_id' => $document->id,
            'edition_number' => 1,
            'revision_number' => 0,
            'version_label' => 'E1-R0',
            'status' => 'draft',
            'version_bump_mode' => 'auto',
            'dibuat_oleh' => $actor->id,
            'diperiksa_oleh' => $reviewer->id,
            'disahkan_oleh' => $approver->id,
            'content_html' => '<p>Konten awal editor dusk</p>',
            'answers_json' => [],
            'content_version' => 1,
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->browse(function (Browser $browser) use ($actor, $reviewer, $document) {
            $browser->loginAs($actor)
                ->visit('/quality/documents/'.$document->id.'/edit')
                ->waitForText('Editor Dokumen QMH')
                ->waitForText('Lock aktif', 15)
                ->press('Simpan Draft')
                ->waitUntil("return document.body.innerText.includes('Status simpan: Tersimpan');", 15)
                ->press('Buka Preview')
                ->waitForText('Preview PDF')
                ->waitUntilMissingText('Memuat preview PDF...', 15)
                ->waitFor('iframe[title="PDF Preview"]', 15)
                ->press('Tutup')
                ->waitUntil("return (() => { const btn = [...document.querySelectorAll('button')].find((el) => el.textContent.includes('Submit untuk Review')); return !!btn && !btn.disabled; })();", 15)
                ->press('Submit untuk Review')
                ->waitFor('#submit-reviewer', 10)
                ->select('#submit-reviewer', (string) $reviewer->id);

            $browser->script("(() => {
                const modal = document.querySelector('#submit-reviewer')?.closest('.relative');
                const submitButton = modal?.querySelector('button.bg-primary-600');
                submitButton?.click();
            })();");

            $browser->waitForLocation('/quality/documents/'.$document->id, 15)
                ->assertSee($document->doc_code);
        });

        $revision->refresh();
        $this->assertSame('in_review', $revision->status);
        $this->assertSame($reviewer->id, $revision->diperiksa_oleh);
    }

    private function createTemplate(int $clause, string $docType, string $name): QmhTemplate
    {
        return QmhTemplate::query()->create([
            'name' => $name,
            'clause' => $clause,
            'doc_type' => $docType,
            'shell_mode' => 'full',
            'orientation_policy' => 'portrait',
            'show_signoff_footer' => true,
            'version' => 1,
            'storage_disk' => 'local',
            'is_active' => true,
            'metadata' => [
                'content_html' => '<p>Template Dusk</p>',
                'form_schema' => [
                    'version' => 1,
                    'doc_type' => $docType,
                    'questions' => [],
                ],
            ],
        ]);
    }

    private function createPdfFixture(string $filename): string
    {
        $directory = storage_path('framework/testing/dusk');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n2 0 obj\n<< /Type /Page >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        if (file_put_contents($path, $pdf) === false) {
            throw new \RuntimeException('Gagal membuat fixture PDF untuk Dusk.');
        }

        return $path;
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

        foreach (['admin', 'reviewer'] as $role) {
            RolePermission::query()->updateOrCreate([
                'role' => $role,
                'permission_id' => $createPermission->id,
            ]);
        }

        RolePermission::query()->updateOrCreate([
            'role' => 'admin',
            'permission_id' => $viewPermission->id,
        ]);
    }
}
