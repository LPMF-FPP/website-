<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = App\Models\User::first();

if (!$user) {
    echo "No users found in database!\n";
    exit(1);
}

echo "User: {$user->name}\n";
echo "Role: {$user->role}\n";

$canManageSettings = Gate::forUser($user)->allows('manage-settings');
echo "Can manage settings: " . ($canManageSettings ? 'YES' : 'NO') . "\n";

if (!$canManageSettings) {
    echo "\nChecking settings...\n";
    $allowed = settings('security.roles.can_manage_settings', ['admin']);
    echo "Allowed roles from settings: " . json_encode($allowed) . "\n";
    echo "User role in allowed: " . (in_array($user->role, $allowed, true) ? 'YES' : 'NO') . "\n";
}
