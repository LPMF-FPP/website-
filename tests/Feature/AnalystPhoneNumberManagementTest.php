<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalystPhoneNumberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_with_phone_number(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $email = 'staff-'.uniqid().'@example.com';

        $this->actingAs($admin)
            ->post(route('analysts.store'), [
                'name' => 'Staff Telepon',
                'email' => $email,
                'phone' => '081234567890',
                'role' => 'analis',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('analysts.index'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'phone' => '081234567890',
        ]);
    }

    public function test_admin_can_update_staff_phone_number(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        /** @var User $staff */
        $staff = User::factory()->create([
            'role' => 'analis',
            'phone' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('analysts.update', $staff), [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => '081298765432',
                'role' => $staff->role,
                'title_prefix' => $staff->title_prefix,
                'title_suffix' => $staff->title_suffix,
                'rank' => $staff->rank,
                'nrp' => $staff->nrp,
                'nip' => $staff->nip,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('analysts.index'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'phone' => '081298765432',
        ]);
    }

    public function test_personnel_staff_tab_can_filter_by_phone_number(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $matchingStaff = User::factory()->create([
            'role' => 'analis',
            'name' => 'Staff Cocok',
            'phone' => '081111111111',
        ]);

        $otherStaff = User::factory()->create([
            'role' => 'analis',
            'name' => 'Staff Lain',
            'phone' => '082222222222',
        ]);

        $this->actingAs($admin)
            ->get(route('personnel.index', ['tab' => 'staff', 'q' => '081111']))
            ->assertOk()
            ->assertSeeText($matchingStaff->name)
            ->assertDontSeeText($otherStaff->name)
            ->assertSeeText('081111111111');
    }
}
