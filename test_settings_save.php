<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

$user = App\Models\User::first();
if (!$user) {
    echo "No users found!\n";
    exit(1);
}

// Simulate authenticated request
$request = Illuminate\Http\Request::create('/settings/save', 'POST', [
    'branding' => [
        'lab_code' => 'TEST',
        'org_name' => 'Test Organization',
    ],
    'numbering' => [
        'ba' => [
            'pattern' => 'BA/{YYYY}/{SEQ:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ],
    ],
]);

$request->headers->set('Accept', 'application/json');
$request->headers->set('Content-Type', 'application/json');
$request->setJson(new \Symfony\Component\HttpFoundation\ParameterBag([
    'branding' => [
        'lab_code' => 'TEST',
        'org_name' => 'Test Organization',
    ],
    'numbering' => [
        'ba' => [
            'pattern' => 'BA/{YYYY}/{SEQ:4}',
            'reset' => 'yearly',
            'start_from' => 1,
        ],
    ],
]));

// Set authenticated user
Auth::login($user);

try {
    $response = $kernel->handle($request);
    
    echo "Status: {$response->getStatusCode()}\n";
    echo "Content:\n";
    echo $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
}
