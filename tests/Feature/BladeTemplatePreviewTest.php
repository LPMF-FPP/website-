<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BladeTemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate an admin user
        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);
        
        $this->actingAs($this->user);
    }

    public function test_preview_returns_html_for_valid_template(): void
    {
        $response = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', [
            'content' => '<html><body><h1>{{ $request->request_number }}</h1></body></html>',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'html',
            ]);

        $this->assertStringContainsString('REQ-2025-0001', $response->json('html'));
    }

    public function test_preview_returns_422_for_missing_content(): void
    {
        $response = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'slug' => 'berita-acara-penerimaan',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'errors',
                'slug',
            ]);
    }

    public function test_preview_returns_422_for_invalid_blade_syntax(): void
    {
        $response = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', [
            'content' => '{{ $undefined->property->that->does->not->exist }}',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Template memiliki error syntax atau runtime.',
                'slug' => 'berita-acara-penerimaan',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'slug',
                'line',
                'hint',
            ]);
    }

    public function test_preview_returns_404_for_nonexistent_template(): void
    {
        $response = $this->postJson('/api/settings/blade-templates/nonexistent-template/preview', [
            'content' => '<html><body>Test</body></html>',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Template tidak ditemukan.',
            ]);
    }

    public function test_preview_returns_422_for_dangerous_functions(): void
    {
        $response = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', [
            'content' => '<?php exec("ls"); ?>',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Template mengandung kode yang tidak diizinkan.',
                'slug' => 'berita-acara-penerimaan',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'error',
                'errors',
                'slug',
            ]);
    }

    public function test_preview_clears_view_cache_after_render(): void
    {
        // First preview
        $response1 = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', [
            'content' => '<html><body>Version 1</body></html>',
        ]);

        $response1->assertStatus(200);
        $this->assertStringContainsString('Version 1', $response1->json('html'));

        // Second preview with different content
        $response2 = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', [
            'content' => '<html><body>Version 2</body></html>',
        ]);

        $response2->assertStatus(200);
        $this->assertStringContainsString('Version 2', $response2->json('html'));
        $this->assertStringNotContainsString('Version 1', $response2->json('html'));
    }

    public function test_preview_works_for_all_template_types(): void
    {
        $templates = [
            'berita-acara-penerimaan',
            'ba-penyerahan',
            'laporan-hasil-uji',
            'form-preparation',
        ];

        foreach ($templates as $template) {
            $response = $this->postJson("/api/settings/blade-templates/{$template}/preview", [
                'content' => '<html><body><h1>Test {{ $generatedAt->format("Y-m-d") }}</h1></body></html>',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure(['success', 'html']);

            $this->assertStringContainsString('Test', $response->json('html'));
        }
    }

    public function test_preview_includes_all_required_variables_for_berita_acara_penerimaan(): void
    {
        $template = <<<'BLADE'
<html>
<body>
    <h1>{{ $request->request_number }}</h1>
    <p>{{ $request->case_number }}</p>
    <p>{{ $request->to_office }}</p>
    <p>{{ $request->received_at->format('d/m/Y') }}</p>
    <p>{{ $request->investigator->rank }} {{ $request->investigator->name }}</p>
    <p>{{ $request->investigator->nrp }}</p>
    @foreach($request->samples as $sample)
        <li>{{ $sample->sample_name }} - {{ $sample->active_substance }}</li>
    @endforeach
    <p>{{ $generatedAt->format('d/m/Y H:i:s') }}</p>
</body>
</html>
BLADE;

        $response = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', [
            'content' => $template,
        ]);

        $response->assertStatus(200);
        $html = $response->json('html');

        // Check all required variables are present in the output
        $this->assertStringContainsString('REQ-2025-0001', $html);
        $this->assertStringContainsString('B/001/I/2025/Reskrim', $html);
        $this->assertStringContainsString('Kepala Sub Satker Farmapol Pusdokkes Polri', $html);
        $this->assertStringContainsString('IPDA', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('12345678', $html);
        $this->assertStringContainsString('Pil Ekstasi Warna Biru', $html);
        $this->assertStringContainsString('MDMA', $html);
    }

    public function test_preview_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->postJson('/api/settings/blade-templates/berita-acara-penerimaan/preview', [
            'content' => '<html><body>Test</body></html>',
        ]);

        $response->assertStatus(401);
    }
}
