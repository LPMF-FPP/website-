<?php

namespace Tests\Feature\Settings;

use App\Models\DocumentTemplate;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplatesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
        settings_forget_cache();
        Storage::fake('local');
    }

    public function test_template_upload_activate_preview_and_delete(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->create('sample.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $upload = $this->actingAs($user)->postJson('/api/settings/templates/upload', [
            'code' => 'LHU',
            'name' => 'Template LHU',
            'file' => $file,
        ])->assertStatus(201);

        $templateId = $upload->json('id');
        $template = DocumentTemplate::findOrFail($templateId);
        Storage::disk('local')->assertExists($template->storage_path);

        $this->actingAs($user)->putJson("/api/settings/templates/{$templateId}/activate", [
            'type' => 'LHU',
        ])->assertOk()
            ->assertJsonPath("active.LHU", 'LHU');

        $this->actingAs($user)->get("/api/settings/templates/{$templateId}/preview")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($user)->deleteJson("/api/settings/templates/{$templateId}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        Storage::disk('local')->assertMissing($template->storage_path);
    }
}
