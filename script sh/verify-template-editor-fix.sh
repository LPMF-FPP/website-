#!/bin/bash

echo "🔍 Verifikasi Fix Template Editor Integration"
echo "=============================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Laravel server is running
echo "📡 Checking Laravel server..."
if curl -s http://127.0.0.1:8000 > /dev/null; then
    echo -e "${GREEN}✓${NC} Laravel server is running"
else
    echo -e "${RED}✗${NC} Laravel server is NOT running"
    echo -e "${YELLOW}  Run: php artisan serve${NC}"
    exit 1
fi

echo ""

# Check if Vite server is running
echo "⚡ Checking Vite dev server..."
if curl -s http://127.0.0.1:5173 > /dev/null; then
    echo -e "${GREEN}✓${NC} Vite dev server is running"
else
    echo -e "${RED}✗${NC} Vite dev server is NOT running"
    echo -e "${YELLOW}  Run: npm run dev${NC}"
    exit 1
fi

echo ""

# Check for PHP syntax errors in fixed file
echo "🔧 Checking PHP syntax..."
if php -l app/Services/DocumentGeneration/DocumentRenderService.php > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} No PHP syntax errors in DocumentRenderService.php"
else
    echo -e "${RED}✗${NC} PHP syntax error detected!"
    php -l app/Services/DocumentGeneration/DocumentRenderService.php
    exit 1
fi

echo ""

# Check if GrapesJS is in package.json
echo "📦 Checking GrapesJS dependency..."
if grep -q "grapesjs" package.json; then
    echo -e "${GREEN}✓${NC} GrapesJS found in package.json"
else
    echo -e "${RED}✗${NC} GrapesJS NOT found in package.json"
    echo -e "${YELLOW}  Run: npm install grapesjs${NC}"
    exit 1
fi

echo ""

# Check if vite.config.js has optimizeDeps
echo "⚙️  Checking Vite config..."
if grep -q "optimizeDeps" vite.config.js; then
    echo -e "${GREEN}✓${NC} Vite optimizeDeps configured"
else
    echo -e "${YELLOW}⚠${NC}  optimizeDeps not found in vite.config.js"
fi

if grep -q "grapesjs" vite.config.js; then
    echo -e "${GREEN}✓${NC} GrapesJS included in Vite optimizeDeps"
else
    echo -e "${YELLOW}⚠${NC}  GrapesJS not in Vite optimizeDeps"
fi

echo ""

# Test API endpoint (requires authentication - will return 401 if not logged in)
echo "🌐 Testing API endpoint..."
RESPONSE=$(curl -s -w "\n%{http_code}" -H "Accept: application/json" http://127.0.0.1:8000/api/settings/document-templates)
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✓${NC} API returned 200 OK"
    echo -e "${GREEN}✓${NC} Response: $(echo $BODY | head -c 100)..."
elif [ "$HTTP_CODE" == "401" ]; then
    echo -e "${YELLOW}⚠${NC}  API returned 401 (expected - requires authentication)"
    echo -e "   Login via browser first, then test manually"
elif [ "$HTTP_CODE" == "500" ]; then
    echo -e "${RED}✗${NC} API returned 500 Internal Server Error"
    echo -e "${RED}   Check storage/logs/laravel.log for details${NC}"
    exit 1
else
    echo -e "${YELLOW}⚠${NC}  API returned HTTP $HTTP_CODE"
fi

echo ""

# Check log for recent errors
echo "📋 Checking recent Laravel logs..."
if [ -f storage/logs/laravel.log ]; then
    RECENT_ERRORS=$(tail -n 50 storage/logs/laravel.log | grep -i "error\|exception" | tail -n 3)
    if [ -z "$RECENT_ERRORS" ]; then
        echo -e "${GREEN}✓${NC} No recent errors in Laravel log"
    else
        echo -e "${YELLOW}⚠${NC}  Recent errors found:"
        echo "$RECENT_ERRORS"
    fi
else
    echo -e "${YELLOW}⚠${NC}  No Laravel log file found"
fi

echo ""
echo "=============================================="
echo "✅ Automated checks complete!"
echo ""
echo "📝 Manual Testing Steps:"
echo "   1. Login as admin"
echo "   2. Go to /settings"
echo "   3. Click 'Template Dokumen' section"
echo "   4. Verify template list loads without errors"
echo "   5. Click 'Buat Template Baru'"
echo "   6. Verify GrapesJS editor loads successfully"
echo ""
echo "See TEMPLATE_EDITOR_FIX.md for detailed verification guide"
echo "=============================================="
