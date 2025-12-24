#!/bin/bash

echo "🔍 Verifikasi Fix GrapesJS Drag & Drop"
echo "======================================"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if servers are running
echo "📡 Checking development servers..."
if curl -s http://127.0.0.1:8000 > /dev/null; then
    echo -e "${GREEN}✓${NC} Laravel server running"
else
    echo -e "${RED}✗${NC} Laravel server NOT running"
    echo -e "${YELLOW}  Run: php artisan serve${NC}"
    exit 1
fi

if curl -s http://127.0.0.1:5173 > /dev/null; then
    echo -e "${GREEN}✓${NC} Vite dev server running"
else
    echo -e "${RED}✗${NC} Vite dev server NOT running"
    echo -e "${YELLOW}  Run: npm run dev${NC}"
    exit 1
fi

echo ""

# Check files exist and have expected content
echo "📄 Checking modified files..."

# Check blade template for x-ignore
if grep -q "x-ignore" resources/views/settings/partials/templates.blade.php; then
    echo -e "${GREEN}✓${NC} Blade template has x-ignore wrapper"
else
    echo -e "${RED}✗${NC} x-ignore wrapper NOT found in blade template"
fi

if grep -q 'id="gjs"' resources/views/settings/partials/templates.blade.php; then
    echo -e "${GREEN}✓${NC} GrapesJS container has id='gjs'"
else
    echo -e "${YELLOW}⚠${NC}  Container id='gjs' not found"
fi

# Check template-editor.js for visibility check
if grep -q "offsetParent === null" resources/js/pages/settings/template-editor.js; then
    echo -e "${GREEN}✓${NC} template-editor.js has visibility check"
else
    echo -e "${RED}✗${NC} Visibility check NOT found in template-editor.js"
fi

if grep -q "editor.refresh()" resources/js/pages/settings/template-editor.js; then
    echo -e "${GREEN}✓${NC} template-editor.js calls editor.refresh()"
else
    echo -e "${YELLOW}⚠${NC}  editor.refresh() not found in template-editor.js"
fi

# Check alpine-component.js for lifecycle management
if grep -q "refreshTemplateEditor" resources/js/pages/settings/alpine-component.js; then
    echo -e "${GREEN}✓${NC} alpine-component.js has refreshTemplateEditor()"
else
    echo -e "${RED}✗${NC} refreshTemplateEditor() NOT found in alpine-component.js"
fi

if grep -q "destroyTemplateEditor" resources/js/pages/settings/alpine-component.js; then
    echo -e "${GREEN}✓${NC} alpine-component.js has destroyTemplateEditor()"
else
    echo -e "${YELLOW}⚠${NC}  destroyTemplateEditor() not found in alpine-component.js"
fi

# Check for section change refresh logic
if grep -q "previousSection !== 'templates'" resources/js/pages/settings/alpine-component.js; then
    echo -e "${GREEN}✓${NC} Section change refresh logic present"
else
    echo -e "${YELLOW}⚠${NC}  Section change refresh logic not found"
fi

echo ""

# Check GrapesJS package
echo "📦 Checking GrapesJS dependency..."
if grep -q '"grapesjs"' package.json; then
    VERSION=$(grep -oP '"grapesjs":\s*"\K[^"]+' package.json)
    echo -e "${GREEN}✓${NC} GrapesJS version: ${VERSION}"
else
    echo -e "${RED}✗${NC} GrapesJS not found in package.json"
fi

echo ""

# Check Vite config
echo "⚙️  Checking Vite configuration..."
if grep -q "grapesjs" vite.config.js; then
    echo -e "${GREEN}✓${NC} GrapesJS in Vite optimizeDeps"
else
    echo -e "${YELLOW}⚠${NC}  GrapesJS not in Vite optimizeDeps"
fi

echo ""

# Check for common issues in code
echo "🔧 Checking for potential issues..."

# Check for double initialization guards
if grep -q "if (this.templateEditorInstance)" resources/js/pages/settings/alpine-component.js; then
    echo -e "${GREEN}✓${NC} Double initialization guard present"
else
    echo -e "${YELLOW}⚠${NC}  No guard against double initialization"
fi

# Check for error handling
if grep -q "catch.*error" resources/js/pages/settings/alpine-component.js; then
    echo -e "${GREEN}✓${NC} Error handling present"
else
    echo -e "${YELLOW}⚠${NC}  Limited error handling"
fi

echo ""
echo "======================================"
echo -e "${BLUE}📊 Automated Checks Complete${NC}"
echo ""
echo -e "${YELLOW}⚠  IMPORTANT: Manual Testing Required!${NC}"
echo ""
echo "🧪 Manual Testing Checklist:"
echo ""
echo "1. Open browser: http://127.0.0.1:8000/settings"
echo "2. Login as admin"
echo "3. Click 'Template Dokumen' section"
echo "4. Click 'New Template' button"
echo "5. Wait for GrapesJS editor to load"
echo "6. ${BLUE}TEST DRAG & DROP:${NC}"
echo "   - Drag 'Section' block to canvas"
echo "   - Drag 'Table' block to canvas"
echo "   - Drag components around (reorder)"
echo "   - Nested drag (component into component)"
echo ""
echo "7. ${BLUE}CHECK CONSOLE (F12):${NC}"
echo "   - NO errors about 'getChildrenContainer'"
echo "   - NO errors about 'dims is undefined'"
echo "   - NO errors about 'pos is undefined'"
echo "   - Should see: 'GrapesJS editor refreshed'"
echo ""
echo "8. ${BLUE}TEST SECTION SWITCHING:${NC}"
echo "   - Click 'Numbering' section (leave templates)"
echo "   - Click 'Template Dokumen' again"
echo "   - Verify drag & drop still works"
echo "   - Check console for 'refreshed' log"
echo ""
echo "9. ${BLUE}REPEAT MULTIPLE TIMES${NC}"
echo "   - Switch sections 5-10 times"
echo "   - Verify no performance degradation"
echo "   - Verify no memory leaks (DevTools Memory tab)"
echo ""
echo "======================================"
echo -e "${GREEN}✅ If all manual tests pass: FIX SUCCESSFUL${NC}"
echo -e "${RED}❌ If drag errors still occur: Check console for details${NC}"
echo ""
echo "See GRAPESJS_DRAG_DROP_FIX.md for detailed guide"
echo "======================================"
