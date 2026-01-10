<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\SystemSetting;

echo "=== Timezone Change Test ===\n\n";

$user = User::where('email', 'labmutufarmapol@gmail.com')->first();
if (!$user) {
    echo "❌ Admin user not found!\n";
    exit(1);
}

echo "✓ User found: {$user->email}\n";
echo "✓ Can manage settings: " . ($user->can('manage-settings') ? 'YES' : 'NO') . "\n\n";

$currentTz = settings('locale.timezone') ?? settings('localization.timezone') ?? 'NOT SET';
echo "Current timezone: {$currentTz}\n";

$timezones = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'];
echo "\nAvailable timezones:\n";
foreach ($timezones as $tz) {
    echo "  - {$tz}\n";
}

$newTimezone = $currentTz === 'Asia/Jakarta' ? 'Asia/Makassar' : 'Asia/Jakarta';
echo "\n📝 Testing timezone change: {$currentTz} → {$newTimezone}\n";

try {
    $request = new \App\Http\Requests\Settings\LocalizationSettingsRequest();
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    $request->replace([
        'localization' => [
            'timezone' => $newTimezone,
            'date_format' => 'DD/MM/YYYY',
            'number_format' => '1.234,56',
            'language' => 'id',
        ],
        'retention' => [
            'storage_driver' => 'public',
        ],
    ]);

    echo "\n🔍 Validating request...\n";
    
    if (!$request->authorize()) {
        echo "❌ Authorization failed!\n";
        exit(1);
    }
    echo "✓ Authorization passed\n";

    $validator = \Illuminate\Support\Facades\Validator::make(
        $request->all(),
        $request->rules(),
        $request->messages()
    );

    if ($validator->fails()) {
        echo "❌ Validation failed:\n";
        foreach ($validator->errors()->all() as $error) {
            echo "  - {$error}\n";
        }
        exit(1);
    }
    echo "✓ Validation passed\n";

    echo "\n💾 Saving timezone change...\n";
    
    $writer = app(\App\Services\Settings\SettingsWriter::class);
    $payload = [
        'locale' => [
            'timezone' => $newTimezone,
            'date_format' => 'DD/MM/YYYY',
            'number_format' => '1.234,56',
            'language' => 'id',
        ],
        'localization' => [
            'timezone' => $newTimezone,
            'date_format' => 'DD/MM/YYYY',
            'number_format' => '1.234,56',
            'language' => 'id',
        ],
        'retention' => [
            'storage_driver' => 'public',
        ],
    ];
    
    $writer->put($payload, 'TEST_TIMEZONE_CHANGE', $user);
    
    settings_forget_cache();
    
    $verifyTz = settings('locale.timezone') ?? settings('localization.timezone');
    echo "\n✅ Timezone saved successfully!\n";
    echo "New timezone: {$verifyTz}\n";
    
    if ($verifyTz === $newTimezone) {
        echo "\n🎉 SUCCESS! Timezone change works correctly.\n";
        
        $dbRecord = SystemSetting::where('key', 'locale.timezone')
            ->orWhere('key', 'localization.timezone')
            ->get();
        
        echo "\n📊 Database records:\n";
        foreach ($dbRecord as $record) {
            echo "  - {$record->key} = {$record->value}\n";
        }
        
        exit(0);
    } else {
        echo "\n❌ FAIL! Timezone mismatch:\n";
        echo "  Expected: {$newTimezone}\n";
        echo "  Got: {$verifyTz}\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error: {$e->getMessage()}\n";
    echo "Trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
