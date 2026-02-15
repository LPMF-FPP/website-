<?php

namespace Tests\Browser\Quality;

use App\Models\Permission;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class QmhPreviewModalTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createQmhPermissions();
    }

    public function test_preview_modal_opens_and_renders_pdf_for_sop_revision(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);

        $document = QmhDocument::query()->create([
            'doc_code' => 'QMH-SOP-DUSK-001',
            'title' => 'SOP Dusk Preview',
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
            'dibuat_oleh' => $user->id,
            'content_html' => '<p>Konten template lama</p>',
            'answers_json' => [
                'purpose' => '<p>Tujuan SOP terstruktur</p>',
                'scope' => '<p>Ruang lingkup SOP terstruktur</p>',
                'procedure' => '<p>Prosedur SOP terstruktur</p>',
            ],
        ]);

        $document->update(['current_revision_id' => $revision->id]);

        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                ->visit('/quality/documents/'.$document->id.'/edit')
                ->waitForText('Editor Dokumen QMH')
                ->waitForText('Isi Dokumen Terstruktur')
                ->press('Buka Preview')
                ->waitForText('Preview PDF')
                ->waitUntilMissingText('Memuat preview PDF...', 15)
                ->waitFor('iframe[title="PDF Preview"]', 15)
                ->assertPresent('iframe[title="PDF Preview"]')
                ->assertScript(
                    'return document.querySelector("iframe[title=\\"PDF Preview\\"]")?.getAttribute("src")?.startsWith("blob:") ?? false;',
                    true
                );
        });
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
