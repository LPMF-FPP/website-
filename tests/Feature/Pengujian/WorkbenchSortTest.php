<?php

declare(strict_types=1);

use App\Livewire\Pengujian\Workbench;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('orders testing requests by smallest receipt number by default', function () {
    $user = User::factory()->create(['role' => 'admin']);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-300',
        'request_number' => 'REQ-300',
        'created_at' => now()->subDay(),
    ]);

    TestRequest::factory()->create([
        'status' => 'in_testing',
        'receipt_number' => 'RESI-100',
        'request_number' => 'REQ-100',
        'created_at' => now()->subHours(12),
    ]);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-200',
        'request_number' => 'REQ-200',
        'created_at' => now()->subHours(6),
    ]);

    Livewire::actingAs($user)
        ->test(Workbench::class)
        ->assertSeeInOrder([
            'RESI-100',
            'RESI-200',
            'RESI-300',
        ]);
});

it('toggles testing request receipt sort direction when header is clicked', function () {
    $user = User::factory()->create(['role' => 'admin']);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-300',
        'request_number' => 'REQ-300',
        'created_at' => now()->subDay(),
    ]);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-100',
        'request_number' => 'REQ-100',
        'created_at' => now()->subHours(12),
    ]);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-200',
        'request_number' => 'REQ-200',
        'created_at' => now()->subHours(6),
    ]);

    Livewire::actingAs($user)
        ->test(Workbench::class)
        ->call('sortBy', 'receipt_number')
        ->assertSeeInOrder([
            'RESI-300',
            'RESI-200',
            'RESI-100',
        ]);
});

it('sorts testing requests by trailing receipt number numerically', function () {
    $user = User::factory()->create(['role' => 'admin']);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-100',
        'request_number' => 'REQ-100',
    ]);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-20',
        'request_number' => 'REQ-20',
    ]);

    TestRequest::factory()->create([
        'status' => 'submitted',
        'receipt_number' => 'RESI-3',
        'request_number' => 'REQ-3',
    ]);

    Livewire::actingAs($user)
        ->test(Workbench::class)
        ->assertSeeInOrder([
            'RESI-3',
            'RESI-20',
            'RESI-100',
        ]);
});
