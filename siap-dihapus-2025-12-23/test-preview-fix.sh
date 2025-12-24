#!/bin/bash
# Test script for blade template preview endpoint fix

echo "🧪 Testing Blade Template Preview Endpoint Fix"
echo "=============================================="
echo ""

# Get CSRF token first (Laravel 11 sanctum setup)
# For testing, we'll use the web interface token or skip CSRF for testing
echo "Note: This API requires authentication. Testing with direct Laravel server..."
echo ""

# Alternative: test with Laravel artisan command
echo "Testing via PHP artisan tinker simulation..."
cat > /tmp/test_preview.php << 'EOF'
<?php
$baseDir = '/home/lpmf-dev/website-';
require $baseDir . '/vendor/autoload.php';
$app = require_once $baseDir . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\Settings\BladeTemplateEditorController;
use Illuminate\Http\Request;

// Create controller instance
$controller = new BladeTemplateEditorController();

// Test 1: Valid content
echo "✅ Test 1: Valid template with valid content\n";
$request = Request::create('/api/settings/blade-templates/berita-acara-penerimaan/preview', 'POST', [
    'content' => '<html><body><h1>Test</h1></body></html>'
]);
$response = $controller->preview($request, 'berita-acara-penerimaan');
$data = json_decode($response->getContent(), true);
echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
echo "Has HTML: " . (isset($data['html']) ? 'yes' : 'no') . "\n\n";

// Test 2: Invalid Blade syntax
echo "❌ Test 2: Invalid Blade syntax (should return 422)\n";
$request = Request::create('/api/settings/blade-templates/berita-acara-penerimaan/preview', 'POST', [
    'content' => '{{ $undefined->variable }}'
]);
$response = $controller->preview($request, 'berita-acara-penerimaan');
echo "HTTP Status: " . $response->getStatusCode() . " (expected: 422)\n";
$data = json_decode($response->getContent(), true);
echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
echo "Message: " . ($data['message'] ?? 'N/A') . "\n";
echo "Error: " . ($data['error'] ?? 'N/A') . "\n\n";

// Test 3: Missing required field
echo "❌ Test 3: Missing required field (should return 422)\n";
$request = Request::create('/api/settings/blade-templates/berita-acara-penerimaan/preview', 'POST', []);
$response = $controller->preview($request, 'berita-acara-penerimaan');
echo "HTTP Status: " . $response->getStatusCode() . " (expected: 422)\n";
$data = json_decode($response->getContent(), true);
echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
echo "Message: " . ($data['message'] ?? 'N/A') . "\n\n";

// Test 4: Non-existent template
echo "❌ Test 4: Non-existent template (should return 404)\n";
$request = Request::create('/api/settings/blade-templates/nonexistent/preview', 'POST', [
    'content' => 'test'
]);
$response = $controller->preview($request, 'nonexistent');
echo "HTTP Status: " . $response->getStatusCode() . " (expected: 404)\n";
$data = json_decode($response->getContent(), true);
echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
echo "Message: " . ($data['message'] ?? 'N/A') . "\n\n";

// Test 5: Dangerous function
echo "❌ Test 5: Dangerous function (should return 422)\n";
$request = Request::create('/api/settings/blade-templates/berita-acara-penerimaan/preview', 'POST', [
    'content' => '<?php exec("ls"); ?>'
]);
$response = $controller->preview($request, 'berita-acara-penerimaan');
echo "HTTP Status: " . $response->getStatusCode() . " (expected: 422)\n";
$data = json_decode($response->getContent(), true);
echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
echo "Message: " . ($data['message'] ?? 'N/A') . "\n\n";

// Test 6: Real template with variables
echo "✅ Test 6: Real template content with Blade variables\n";
$request = Request::create('/api/settings/blade-templates/berita-acara-penerimaan/preview', 'POST', [
    'content' => '<html><body><h1>{{ $request->request_number }}</h1><p>{{ $generatedAt->format("d/m/Y") }}</p></body></html>'
]);
$response = $controller->preview($request, 'berita-acara-penerimaan');
$data = json_decode($response->getContent(), true);
echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
if (isset($data['html'])) {
    echo "HTML contains REQ-2025-0001: " . (strpos($data['html'], 'REQ-2025-0001') !== false ? 'yes' : 'no') . "\n";
}
echo "\n";

echo "✅ All tests complete!\n\n";
echo "Expected Results:\n";
echo "- Test 1: success=true, html present\n";
echo "- Test 2: HTTP 422, error message about syntax/runtime\n";
echo "- Test 3: HTTP 422, validation error about content field\n";
echo "- Test 4: HTTP 404, template not found\n";
echo "- Test 5: HTTP 422, dangerous code detected\n";
echo "- Test 6: success=true, html with REQ-2025-0001 and date\n";
EOF

cd /home/lpmf-dev/website- && php /tmp/test_preview.php
rm -f /tmp/test_preview.php
