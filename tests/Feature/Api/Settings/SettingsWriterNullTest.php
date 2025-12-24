<?php

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Settings\SettingsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('handles null values in retention settings without database constraint violation', function () {
    $writer = app(SettingsWriter::class);

    // This should NOT throw a database constraint violation
    $writer->put([
        'retention' => [
            'storage_driver' => 'local',
            'storage_folder_path' => 'test-folder',
            'purge_after_days' => null, // Null value
            'export_filename_pattern' => '',
        ],
    ], 'TEST_NULL_VALUES', $this->user);

    // Verify non-null values were saved
    $this->assertDatabaseHas('settings', [
        'key' => 'retention.storage_driver',
        'value' => json_encode('local'),
    ]);

    $this->assertDatabaseHas('settings', [
        'key' => 'retention.storage_folder_path',
        'value' => json_encode('test-folder'),
    ]);

    // Verify null value was NOT inserted (key should not exist)
    $count = SystemSetting::where('key', 'retention.purge_after_days')->count();
    expect($count)->toBe(0, 'Null value should not create a database row');
});

it('handles nested arrays with all null values', function () {
    $writer = app(SettingsWriter::class);

    // Should not fail even if entire nested array contains nulls
    $writer->put([
        'notifications' => [
            'email_enabled' => null,
            'whatsapp_enabled' => null,
        ],
    ], 'TEST_ALL_NULL', $this->user);

    // Since all values are null, no records should be created
    $count = SystemSetting::where('key', 'LIKE', 'notifications.%')->count();
    expect($count)->toBe(0, 'All-null nested array should not create any records');
});

it('handles mixed null and non-null values correctly', function () {
    $writer = app(SettingsWriter::class);

    $writer->put([
        'locale' => [
            'timezone' => 'Asia/Jakarta',
            'date_format' => null, // Should be skipped
            'language' => 'id',
        ],
    ], 'TEST_MIXED_NULL', $this->user);

    // Non-null values saved
    $this->assertDatabaseHas('settings', [
        'key' => 'locale.timezone',
        'value' => json_encode('Asia/Jakarta'),
    ]);

    $this->assertDatabaseHas('settings', [
        'key' => 'locale.language',
        'value' => json_encode('id'),
    ]);

    // Null value not saved
    $count = SystemSetting::where('key', 'locale.date_format')->count();
    expect($count)->toBe(0);
});

it('deletes existing setting when updated to null', function () {
    // Pre-populate a setting
    SystemSetting::create([
        'key' => 'retention.purge_after_days',
        'value' => 90,
        'updated_by' => $this->user->id,
    ]);

    $this->assertDatabaseHas('settings', [
        'key' => 'retention.purge_after_days',
    ]);

    $writer = app(SettingsWriter::class);

    // Update to null should delete the record
    $writer->put([
        'retention' => [
            'purge_after_days' => null,
        ],
    ], 'TEST_DELETE_ON_NULL', $this->user);

    // Record should be gone
    $this->assertDatabaseMissing('settings', [
        'key' => 'retention.purge_after_days',
    ]);
});
