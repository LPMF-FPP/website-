<?php

namespace Tests\Feature\Changelog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_page_displays_the_latest_release_and_existing_history(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('changelogs.index'));

        $response
            ->assertOk()
            ->assertSee('v2.7.1')
            ->assertSee('Ketahanan WhatsApp AI')
            ->assertSee('v2.7.0')
            ->assertSee('Inventori Desktop, Pemusnahan Sampel')
            ->assertSee('v2.6.7')
            ->assertSee('6d445d4')
            ->assertSee('v2.6.3')
            ->assertSee('Deploy Hardening')
            ->assertSee('Verifikasi Host SSH');
    }
}
