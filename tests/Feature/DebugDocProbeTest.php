<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DebugDocProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_doc_probe_route_works(): void
    {
        $user = User::factory()->create();

        // Use real storage for this test
        $response = $this->actingAs($user)->get('/debug/doc-probe');

        // Assert response is successful
        $response->assertStatus(200);
        
        // Assert JSON structure
        $response->assertJsonStructure([
            'success',
            'root',
            'path',
            'full_path',
            'exists',
            'file_size',
            'timestamp',
            'folder_exists',
            'message',
        ]);

        // Assert success is true
        $response->assertJson([
            'success' => true,
            'path' => 'investigators/__probe_route.txt',
            'exists' => true,
            'folder_exists' => true,
        ]);

        // Verify file exists in storage
        $disk = Storage::disk('public');
        $this->assertTrue($disk->exists('investigators/__probe_route.txt'));
        
        // Verify content contains expected data
        $content = $disk->get('investigators/__probe_route.txt');
        $this->assertStringContainsString('Probe timestamp:', $content);
        $this->assertStringContainsString('Route: GET /debug/doc-probe', $content);
        $this->assertStringContainsString('Disk: public', $content);
    }

    public function test_debug_doc_probe_creates_investigators_folder(): void
    {
        $user = User::factory()->create();
        $disk = Storage::disk('public');
        
        // Delete folder if exists (for clean test)
        if ($disk->exists('investigators/__probe_route.txt')) {
            $disk->delete('investigators/__probe_route.txt');
        }

        // Call the probe route
        $response = $this->actingAs($user)->get('/debug/doc-probe');
        
        // Assert folder was created
        $response->assertStatus(200);
        $response->assertJson(['folder_exists' => true]);
        
        // Verify folder exists
        $this->assertTrue($disk->exists('investigators'));
    }

    public function test_debug_doc_probe_returns_correct_config_root(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/debug/doc-probe');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Verify root matches config
        $expectedRoot = config('filesystems.disks.public.root');
        $this->assertEquals($expectedRoot, $data['root']);
        
        // Verify full_path is constructed correctly
        $this->assertStringContainsString('investigators', $data['full_path']);
        $this->assertStringContainsString('__probe_route.txt', $data['full_path']);
    }
}
