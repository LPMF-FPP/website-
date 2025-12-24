<?php

// Quick test to debug the preview endpoint
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a test user
$user = \App\Models\User::factory()->create(['role' => 'admin']);

// Simulate authenticated request
\Illuminate\Support\Facades\Auth::login($user);

// Call the controller directly
$controller = new \App\Http\Controllers\Api\Settings\BladeTemplateEditorController();
$request = \Illuminate\Http\Request::create(
    '/api/settings/blade-templates/berita-acara-penerimaan/preview',
    'POST',
    ['content' => '<html><body><h1>{{ $request->request_number }}</h1></body></html>']
);
$request->setUserResolver(fn() => $user);

try {
    $response = $controller->preview($request, 'berita-acara-penerimaan');
    $data = json_decode($response->getContent(), true);
    
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Response:\n";
    print_r($data);
    
    if (isset($data['error'])) {
        echo "\nError details:\n";
        echo $data['error'] . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
