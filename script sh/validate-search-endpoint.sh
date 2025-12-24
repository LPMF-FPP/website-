#!/bin/bash
set -e

echo "=== SEARCH ENDPOINT VALIDATION ==="
echo ""

echo "1. Verifying route exists..."
php artisan route:list | grep -E "search/data|search\.data" && echo "✓ Route exists" || (echo "✗ Route not found" && exit 1)
echo ""

echo "2. Testing unauthenticated request (should return 401)..."
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Accept: application/json" "http://127.0.0.1:8000/search/data?q=test&doc_type=all&page_people=1&per_page_people=6&page_docs=1&per_page_docs=6")
if [ "$STATUS" = "401" ]; then
    echo "✓ Returns 401 for unauthenticated request (correct)"
else
    echo "✗ Expected 401, got $STATUS"
    exit 1
fi
echo ""

echo "3. Checking SearchController exists..."
[ -f app/Http/Controllers/SearchController.php ] && echo "✓ SearchController exists" || (echo "✗ SearchController not found" && exit 1)
echo ""

echo "4. Checking PHP syntax..."
php -l app/Http/Controllers/SearchController.php > /dev/null && echo "✓ No syntax errors" || exit 1
echo ""

echo "=== ALL VALIDATIONS PASSED ==="
echo ""
echo "Frontend should now receive:"
echo "  - HTTP 401 when unauthenticated"
echo "  - HTTP 200 with JSON when authenticated"
echo ""
echo "To test with authenticated session, visit /search in browser and check DevTools Network tab."
