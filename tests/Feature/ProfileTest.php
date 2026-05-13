<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGoogleDriveToken;
use App\Services\GoogleDriveOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_warns_when_google_drive_token_is_revoked(): void
    {
        $user = User::factory()->create();
        UserGoogleDriveToken::create([
            'user_id' => $user->id,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'revoked-refresh-token',
            'expires_at' => now()->subMinute(),
        ]);

        $oauth = \Mockery::mock(GoogleDriveOAuthService::class);
        $oauth->shouldReceive('accessTokenFor')
            ->once()
            ->with(\Mockery::on(fn (User $candidate): bool => $candidate->is($user)))
            ->andThrow(new RuntimeException('Google Drive token refresh failed: Token has been expired or revoked.'));
        app()->instance(GoogleDriveOAuthService::class, $oauth);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Google Drive perlu dihubungkan ulang.')
            ->assertSee('Token Google Drive sudah tidak valid atau dicabut oleh Google.')
            ->assertSee('Putuskan Google Drive');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();
        $newEmail = 'updated-'.uniqid().'@example.com';

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $newEmail,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame($newEmail, $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_identity_fields_can_be_updated(): void
    {
        $user = User::factory()->create([
            'title_prefix' => null,
            'title_suffix' => null,
            'rank' => null,
            'nrp' => null,
            'nip' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Petugas LPMF',
                'email' => $user->email,
                'phone' => '081234567890',
                'title_prefix' => 'Dr.',
                'title_suffix' => 'S.Farm., Apt.',
                'rank' => 'AKP',
                'nrp' => '70040687',
                'nip' => '198001012006041001',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Dr.', $user->title_prefix);
        $this->assertSame('S.Farm., Apt.', $user->title_suffix);
        $this->assertSame('AKP', $user->rank);
        $this->assertSame('70040687', $user->nrp);
        $this->assertSame('198001012006041001', $user->nip);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
