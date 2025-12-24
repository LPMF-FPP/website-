<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
});

test('dapat mengupdate localization dan retention settings', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => 'uploads/documents',
                'purge_after_days' => 365,
                'export_filename_pattern' => '{date}-{type}',
            ],
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['localization', 'retention']);
});

test('menerima purge_after_days kosong sebagai null', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => 'uploads',
                'purge_after_days' => '',
                'export_filename_pattern' => '',
            ],
        ]);

    $response->assertStatus(200);
});

test('menolak absolute path', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => '/var/www/uploads',
                'purge_after_days' => 365,
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['retention.storage_folder_path']);
});

test('menolak directory traversal', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => 'uploads/../../etc',
                'purge_after_days' => 365,
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['retention.storage_folder_path']);
});

test('validasi timezone harus dari daftar yang diizinkan', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'Invalid/Timezone',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => 'uploads',
                'purge_after_days' => 365,
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['localization.timezone']);
});

test('validasi purge_after_days minimum 30 hari', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/api/settings/localization-retention', [
            'localization' => [
                'timezone' => 'Asia/Jakarta',
                'date_format' => 'DD/MM/YYYY',
                'number_format' => '1.234,56',
                'language' => 'id',
            ],
            'retention' => [
                'storage_driver' => 'local',
                'storage_folder_path' => 'uploads',
                'purge_after_days' => 10,
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['retention.purge_after_days']);
});
