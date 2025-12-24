#!/bin/bash

# Settings Preview Test Script
# Tests if preview functionality is working correctly

echo "🧪 Testing Settings Preview Functionality"
echo "=========================================="
echo ""

# Check if server is running
echo "1️⃣ Checking if Laravel server is running..."
if curl -s http://localhost:8000 > /dev/null; then
    echo "   ✅ Server is running"
else
    echo "   ❌ Server is not running. Start with: php artisan serve"
    exit 1
fi

echo ""
echo "2️⃣ Checking if Vite build is up to date..."
if [ -f "public/build/manifest.json" ]; then
    echo "   ✅ Vite build found"
else
    echo "   ❌ Vite build not found. Run: npm run build"
    exit 1
fi

echo ""
echo "3️⃣ Testing Preview Endpoints..."

# Test numbering preview endpoint
echo "   → Testing POST /api/settings/numbering/preview"
RESPONSE=$(curl -s -X POST http://localhost:8000/api/settings/numbering/preview \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"scope":"sample_code","pattern":"SMP-{YYYY}{MM}-{SEQ:4}","reset":"yearly","start_from":1}' \
  -w "\n%{http_code}")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

if [ "$HTTP_CODE" == "200" ] || [ "$HTTP_CODE" == "401" ] || [ "$HTTP_CODE" == "419" ]; then
    echo "   ✅ Endpoint accessible (HTTP $HTTP_CODE)"
    if [ "$HTTP_CODE" == "401" ] || [ "$HTTP_CODE" == "419" ]; then
        echo "      ℹ️  Authentication required - this is expected"
    fi
else
    echo "   ❌ Endpoint error (HTTP $HTTP_CODE)"
fi

echo ""
echo "4️⃣ Checking JavaScript files..."
if grep -q "testPreview(scope)" resources/js/pages/settings/alpine-component.js; then
    echo "   ✅ testPreview wrapper found in alpine-component.js"
else
    echo "   ❌ testPreview wrapper not found"
fi

if grep -q "previewPdf()" resources/js/pages/settings/alpine-component.js; then
    echo "   ✅ previewPdf wrapper found in alpine-component.js"
else
    echo "   ❌ previewPdf wrapper not found"
fi

if grep -q "console.log.*testPreview" resources/js/pages/settings/index.js; then
    echo "   ✅ Debug logging found in SettingsClient"
else
    echo "   ❌ Debug logging not found"
fi

echo ""
echo "5️⃣ Checking Blade templates..."
if grep -q '@click.*="testPreview(scope)"' resources/views/settings/partials/numbering.blade.php; then
    echo "   ✅ testPreview binding found in numbering.blade.php"
else
    echo "   ⚠️  testPreview binding might be using old pattern"
fi

if grep -q '@click.*="previewPdf()"' resources/views/settings/partials/branding.blade.php; then
    echo "   ✅ previewPdf binding found in branding.blade.php"
else
    echo "   ⚠️  previewPdf binding might be using old pattern"
fi

echo ""
echo "=========================================="
echo "📋 Manual Testing Instructions:"
echo "=========================================="
echo ""
echo "1. Open browser to: http://localhost:8000/settings"
echo "2. Open Browser DevTools Console (F12)"
echo "3. Click 'Test Preview' button on any numbering scope"
echo ""
echo "Expected Console Output:"
echo "  🔍 testPreview called"
echo "  SettingsClient.testPreview called"
echo "  → POST /api/settings/numbering/preview"
echo "  ✓ Preview response: {...}"
echo ""
echo "Expected Network Tab:"
echo "  POST /api/settings/numbering/preview (Status: 200)"
echo ""
echo "Expected UI:"
echo "  - Button shows 'Testing...' during request"
echo "  - Preview text appears in gray box"
echo "  - Success message: 'Preview berhasil!'"
echo ""
echo "=========================================="
echo "✅ Automated checks completed!"
echo "   Please perform manual testing in browser."
echo "=========================================="
