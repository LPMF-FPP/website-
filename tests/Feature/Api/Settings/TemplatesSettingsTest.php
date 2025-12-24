<?php

namespace Tests\Feature\Api\Settings;

use App\Models\DocumentTemplate;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplatesSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_list_templates(): void
    {
        DocumentTemplate::factory()->count(3)->create();
        
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/settings/templates');

        $response->assertOk()
            ->assertJsonCount(3);
    }

    public function test_can_upload_template(): void
    {
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('template.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $payload = [
            'code' => 'LHU_V2',
            'name' => 'Laporan Hasil Uji v2',
            'file' => $file,
        ];

        $response = $this->postJson('/api/settings/templates/upload', $payload);

        $response->assertCreated()
            ->assertJsonPath('code', 'LHU_V2')
            ->assertJsonPath('name', 'Laporan Hasil Uji v2');

        $this->assertDatabaseHas('document_templates', [
            'code' => 'LHU_V2',
            'name' => 'Laporan Hasil Uji v2',
        ]);

        // Verify file was stored
        $template = DocumentTemplate::where('code', 'LHU_V2')->first();
        Storage::disk('local')->assertExists($template->storage_path);
    }

    public function test_uploading_same_code_updates_existing_template(): void
    {
        $this->actingAs($this->admin);

        $existing = DocumentTemplate::factory()->create([
            'code' => 'BA_PENERIMAAN',
            'name' => 'Old Name',
        ]);

        $file = UploadedFile::fake()->create('new-template.docx', 100);

        $payload = [
            'code' => 'BA_PENERIMAAN',
            'name' => 'New Name',
            'file' => $file,
        ];

        $response = $this->postJson('/api/settings/templates/upload', $payload);

        $response->assertCreated()
            ->assertJsonPath('name', 'New Name');

        // Should update, not create new
        $this->assertDatabaseCount('document_templates', 1);
        $this->assertDatabaseHas('document_templates', [
            'code' => 'BA_PENERIMAAN',
            'name' => 'New Name',
        ]);
    }

    public function test_can_activate_template(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create(['code' => 'LHU_MAIN']);

        $payload = [
            'type' => 'lhu',
        ];

        $response = $this->putJson("/api/settings/templates/{$template->id}/activate", $payload);

        $response->assertOk()
            ->assertJsonPath('active.lhu', 'LHU_MAIN');

        // The setting could be stored either as templates.active or templates.active.lhu
        // depending on how SettingsWriter handles nested arrays with dot notation keys
        $settings = SystemSetting::where('key', 'like', 'templates.active%')->get();
        $this->assertGreaterThan(0, $settings->count(), 'Should have at least one templates.active setting');
        
        // Check that lhu template is activated
        $snapshot = settings_nest(settings());
        $this->assertEquals('LHU_MAIN', data_get($snapshot, 'templates.active.lhu'));
    }

    public function test_can_delete_template(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create();
        
        // Create fake file
        Storage::disk('local')->put($template->storage_path, 'fake content');

        $response = $this->deleteJson("/api/settings/templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('document_templates', [
            'id' => $template->id,
        ]);

        // File should be deleted
        Storage::disk('local')->assertMissing($template->storage_path);
    }

    public function test_can_preview_template(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'storage_path' => 'templates/test.docx',
        ]);

        Storage::disk('local')->put($template->storage_path, 'fake docx content');

        $response = $this->get("/api/settings/templates/{$template->id}/preview");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertEquals('fake docx content', $response->streamedContent());
    }

    public function test_preview_returns_404_if_file_missing(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'storage_path' => 'templates/missing.docx',
        ]);

        $response = $this->get("/api/settings/templates/{$template->id}/preview");

        $response->assertNotFound();
    }

    public function test_validates_upload_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/settings/templates/upload', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'file']);
    }

    public function test_validates_activate_type_required(): void
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create();

        $response = $this->putJson("/api/settings/templates/{$template->id}/activate", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/settings/templates');

        $response->assertUnauthorized();
    }

    public function test_requires_authorization(): void
    {
        $user = User::factory()->create(['role' => 'investigator']);
        $this->actingAs($user);

        $response = $this->getJson('/api/settings/templates');

        $response->assertForbidden();
    }

    public function test_upload_requires_authorization(): void
    {
        $user = User::factory()->create(['role' => 'investigator']);
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('template.docx', 100);

        $response = $this->postJson('/api/settings/templates/upload', [
            'code' => 'TEST',
            'name' => 'Test',
            'file' => $file,
        ]);

        $response->assertForbidden();
    }

    public function test_delete_requires_authorization(): void
    {
        $user = User::factory()->create(['role' => 'investigator']);
        $this->actingAs($user);

        $template = DocumentTemplate::factory()->create();

        $response = $this->deleteJson("/api/settings/templates/{$template->id}");

        $response->assertForbidden();
    }

    public function test_preview_requires_authorization(): void
    {
        $user = User::factory()->create(['role' => 'investigator']);
        $this->actingAs($user);

        $template = DocumentTemplate::factory()->create();

        $response = $this->get("/api/settings/templates/{$template->id}/preview");

        $response->assertForbidden();
    }
}
