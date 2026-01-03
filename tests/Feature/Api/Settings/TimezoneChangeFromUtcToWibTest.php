<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
});

test('perubahan timezone dari UTC ke WIB (Asia/Jakarta)', function () {
    // Step 1: Set timezone to UTC first
    $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'UTC',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'public',
                'purge_after_days' => 365,
            ],
        ])
        ->assertStatus(200);

    // Verify timezone is now UTC via time-preview endpoint
    $response = $this->actingAs($this->user)
        ->getJson('/api/settings/localization/time-preview');
    $response->assertStatus(200);
    $initialTimezone = $response->json('app_timezone');
    expect($initialTimezone)->toBe('UTC');

    // Step 2: Change timezone from UTC to WIB (Asia/Jakarta)
    $response = $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'Asia/Jakarta',  // WIB = Western Indonesian Time
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'public',
                'purge_after_days' => 365,
            ],
        ]);

    // Assert the request was successful
    $response->assertStatus(200)
        ->assertJsonStructure(['localization', 'retention']);

    // Step 3: Verify the timezone has been changed to Asia/Jakarta
    $newResponse = $this->actingAs($this->user)
        ->getJson('/api/settings/localization/time-preview');

    $newResponse->assertStatus(200);
    $newTimezone = $newResponse->json('app_timezone');

    // Assert timezone is now Asia/Jakarta (WIB)
    expect($newTimezone)->toBe('Asia/Jakarta');

    // Verify the localization settings returned
    expect($response->json('localization.timezone'))->toBe('Asia/Jakarta');
});
