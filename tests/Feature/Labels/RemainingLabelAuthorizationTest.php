<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('forbids non admin user without remaining label permissions', function (): void {
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->get(route('labels.remaining.sheet', 1))
        ->assertForbidden();
});

it('allows admin user to access remaining label endpoint', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('labels.remaining.sheet', 1))
        ->assertStatus(302);
});
