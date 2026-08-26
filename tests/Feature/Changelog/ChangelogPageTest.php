<?php

namespace Tests\Feature\Changelog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_page_displays_the_latest_deploy_hardening_entry(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('changelogs.index'));

        $response
            ->assertOk()
            ->assertSee('v2.6.3')
            ->assertSee('Deploy Hardening')
            ->assertSee('Verifikasi Host SSH');
    }
}
