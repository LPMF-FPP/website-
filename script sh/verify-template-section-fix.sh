#!/bin/bash

echo "🔍 Template Dokumen Section - Complete Fix Verification"
echo "======================================================="
echo ""

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Check servers
echo "📡 Checking servers..."
if curl -s http://127.0.0.1:8000 > /dev/null; then
    echo -e "${GREEN}✓${NC} Laravel server running"
else
    echo -e "${RED}✗${NC} Laravel server NOT running - Run: php artisan serve"
    exit 1
fi

if curl -s http://127.0.0.1:5173 > /dev/null; then
    echo -e "${GREEN}✓${NC} Vite dev server running"
else
    echo -e "${RED}✗${NC} Vite NOT running - Run: npm run dev"
    exit 1
fi

echo ""
echo "📄 Checking file modifications..."

# Backend fixes
if grep -q "try {" app/Http/Controllers/Api/Settings/DocumentTemplateController.php; then
    echo -e "${GREEN}✓${NC} Backend: Error handling added"
else
    echo -e "${RED}✗${NC} Backend: Missing error handling"
fi

if grep -q "'success' => true" app/Http/Controllers/Api/Settings/DocumentTemplateController.php; then
    echo -e "${GREEN}✓${NC} Backend: Standardized JSON response"
else
    echo -e "${YELLOW}⚠${NC}  Backend: Response format check failed"
fi

# Frontend fixes
if grep -q "import grapesjs from 'grapesjs'" resources/js/pages/settings/template-editor.js; then
    echo -e "${GREEN}✓${NC} Frontend: GrapesJS static import (Vite compatible)"
else
    echo -e "${RED}✗${NC} Frontend: Still using dynamic import (BROKEN)"
fi

if grep -q "credentials: 'same-origin'" resources/js/pages/settings/alpine-component.js; then
    echo -e "${GREEN}✓${NC} Frontend: Credentials included in fetch"
else
    echo -e "${YELLOW}⚠${NC}  Frontend: Missing credentials (may cause auth issues)"
fi

if grep -q "console.log('📝 Starting new template" resources/js/pages/settings/alpine-component.js; then
    echo -e "${GREEN}✓${NC} Frontend: Enhanced logging for debugging"
else
    echo -e "${YELLOW}⚠${NC}  Frontend: Missing debug logs"
fi

if grep -q "x-ignore" resources/views/settings/partials/templates.blade.php; then
    echo -e "${GREEN}✓${NC} View: x-ignore wrapper present"
else
    echo -e "${YELLOW}⚠${NC}  View: Missing x-ignore (Alpine may interfere)"
fi

echo ""
echo "📦 Checking dependencies..."

if grep -q '"grapesjs"' package.json; then
    VERSION=$(grep -oP '"grapesjs":\s*"\K[^"]+' package.json)
    echo -e "${GREEN}✓${NC} GrapesJS installed: ${VERSION}"
else
    echo -e "${RED}✗${NC} GrapesJS NOT installed - Run: npm install grapesjs"
fi

if grep -q "optimizeDeps" vite.config.js && grep -q "grapesjs" vite.config.js; then
    echo -e "${GREEN}✓${NC} Vite: GrapesJS in optimizeDeps"
else
    echo -e "${YELLOW}⚠${NC}  Vite: GrapesJS not optimized (may cause slow loading)"
fi

echo ""
echo "======================================================="
echo -e "${BLUE}📊 Automated Checks Complete!${NC}"
echo ""
echo -e "${YELLOW}⚠  CRITICAL: Manual Testing Required!${NC}"
echo ""
echo "🧪 Quick Manual Test:"
echo "  1. Login as admin"
echo "  2. Go to /settings"
echo "  3. Click 'Template Dokumen' section"
echo "  4. Check console (F12): Should see '✅ Templates loaded'"
echo "  5. Click '+ New Template'"
echo "  6. Check console: Should see '📝 Starting new template...'"
echo "  7. Check console: Should see '✅ GrapesJS editor initialized'"
echo "  8. Drag 'Section' block to canvas"
echo "  9. NO errors should appear (especially NO Sorter.ts errors)"
echo " 10. Success!"
echo ""
echo "📖 Full Testing Guide: See TEMPLATE_SECTION_COMPLETE_FIX.md"
echo "======================================================="
