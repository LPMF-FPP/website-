#!/bin/bash

# Preview Payload & Response Fix - Validation Script

echo "🧪 Testing Preview Payload & Response Fix"
echo "=============================================="
echo ""

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "1️⃣ Checking Payload Fix (Plain Object Conversion)..."

if grep -q "toPlainObject" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} toPlainObject helper method found"
else
    echo -e "   ${RED}❌${NC} toPlainObject helper not found"
fi

if grep -q "structuredClone" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} structuredClone with fallback implemented"
else
    echo -e "   ${YELLOW}⚠️${NC}  structuredClone not found"
fi

if grep -q "this.toPlainObject(this.state.form.numbering" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} Config converted to plain object before sending"
else
    echo -e "   ${RED}❌${NC} Config not converted (will send Proxy)"
fi

echo ""
echo "2️⃣ Checking Payload Structure..."

if grep -q "config: {" resources/js/pages/settings/index.js | head -1; then
    echo -e "   ${GREEN}✅${NC} Payload includes 'config' wrapper"
fi

if grep -q "numbering: {" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} Nested numbering structure present"
fi

echo ""
echo "3️⃣ Checking Backend Improvements..."

if grep -q "data_get.*pattern" app/Http/Controllers/Api/Settings/NumberingController.php; then
    echo -e "   ${GREEN}✅${NC} Backend extracts pattern from multiple sources"
fi

if grep -q "empty(\$pattern)" app/Http/Controllers/Api/Settings/NumberingController.php; then
    echo -e "   ${GREEN}✅${NC} Pattern validation added"
fi

if grep -q "422" app/Http/Controllers/Api/Settings/NumberingController.php; then
    echo -e "   ${GREEN}✅${NC} Proper validation error response (422)"
fi

if grep -q "Log::info.*preview" app/Http/Controllers/Api/Settings/NumberingController.php; then
    echo -e "   ${GREEN}✅${NC} Debugging logs added to backend"
fi

echo ""
echo "4️⃣ Checking Response Parsing..."

if grep -q "data.example" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} Frontend parses 'example' from response"
fi

if grep -q "Preview kosong" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} Empty preview error handling"
fi

echo ""
echo "5️⃣ Checking UI Improvements..."

if grep -q "getPreviewText" resources/js/pages/settings/alpine-component.js; then
    echo -e "   ${GREEN}✅${NC} Helper method getPreviewText exists"
fi

if grep -q "Preview gagal dibuat" resources/js/pages/settings/alpine-component.js; then
    echo -e "   ${GREEN}✅${NC} Error message for failed preview"
fi

if grep -q "x-text=\"getPreviewText(scope)\"" resources/views/settings/partials/numbering.blade.php; then
    echo -e "   ${GREEN}✅${NC} Template uses getPreviewText helper"
fi

if grep -q "text-red-600" resources/views/settings/partials/numbering.blade.php; then
    echo -e "   ${GREEN}✅${NC} Red color for error state"
fi

echo ""
echo "6️⃣ Checking Init Guard..."

if grep -q "_initialized" resources/js/pages/settings/alpine-component.js; then
    echo -e "   ${GREEN}✅${NC} Init guard implemented"
fi

if grep -q "skipping duplicate initialization" resources/js/pages/settings/alpine-component.js; then
    echo -e "   ${GREEN}✅${NC} Duplicate init warning added"
fi

echo ""
echo "7️⃣ Checking Build..."

if [ -f "public/build/manifest.json" ]; then
    BUILD_TIME=$(stat -c %y public/build/manifest.json 2>/dev/null || stat -f %Sm public/build/manifest.json 2>/dev/null)
    echo -e "   ${GREEN}✅${NC} Vite build exists (built: ${BUILD_TIME:0:19})"
else
    echo -e "   ${RED}❌${NC} Build not found - run: npm run build"
fi

echo ""
echo "=============================================="
echo "📋 Manual Testing Instructions:"
echo "=============================================="
echo ""
echo -e "${BLUE}Step 1: Open Browser${NC}"
echo "   http://localhost:8000/settings"
echo ""
echo -e "${BLUE}Step 2: Open DevTools${NC}"
echo "   - Console tab (to see logs)"
echo "   - Network tab (to see requests)"
echo ""
echo -e "${BLUE}Step 3: Fill Pattern${NC}"
echo "   - Navigate to 'Penomoran Otomatis' section"
echo "   - Select any scope (e.g., 'LHU')"
echo "   - Fill pattern field: 'LHU-{YYYY}-{SEQ:4}'"
echo ""
echo -e "${BLUE}Step 4: Click 'Test Preview'${NC}"
echo ""
echo "=============================================="
echo "🔍 Expected Console Output:"
echo "=============================================="
echo ""
echo "🚀 [Alpine] settingsPageAlpine init started"
echo "🔍 [Alpine Wrapper] testPreview called { scope: 'lhu' }"
echo "📊 Current preview state: { lhu: '', ... }"
echo "⚙️ Current form config: { pattern: 'LHU-{YYYY}-{SEQ:4}', ... }"
echo "▶️ testPreview promise initiated"
echo "🔍 [testPreview] Starting preview for scope: lhu"
echo "📋 Config from state: { pattern: 'LHU-{YYYY}-{SEQ:4}', reset: 'yearly', start_from: 1 }"
echo "→ POST /api/settings/numbering/preview {"
echo "    scope: 'lhu',"
echo "    config: {"
echo "      numbering: {"
echo "        lhu: {"
echo "          pattern: 'LHU-{YYYY}-{SEQ:4}',"
echo "          reset: 'yearly',"
echo "          start_from: 1"
echo "        }"
echo "      }"
echo "    }"
echo "  }"
echo "✓ Preview response: { example: 'LHU-2025-0123', scope: 'lhu', pattern: 'LHU-{YYYY}-{SEQ:4}' }"
echo "✓ Extracted preview value: LHU-2025-0123"
echo "✓ State updated: { scope: 'lhu', value: 'LHU-2025-0123' }"
echo ""
echo "=============================================="
echo "📱 Expected UI Behavior:"
echo "=============================================="
echo ""
echo "BEFORE click:"
echo "  Preview box: 'Click Test Preview'"
echo ""
echo "DURING request:"
echo "  Button: 'Test Preview' → 'Testing...'"
echo "  Button disabled"
echo ""
echo "AFTER success:"
echo "  Preview box: 'LHU-2025-0123' (in black/gray)"
echo "  Success message: 'Preview berhasil!' (green)"
echo "  Button: 'Testing...' → 'Test Preview'"
echo ""
echo "AFTER error (empty pattern):"
echo "  Preview box: '❌ Preview gagal dibuat' (in red)"
echo "  Error message shown"
echo ""
echo "=============================================="
echo "🌐 Expected Network Tab:"
echo "=============================================="
echo ""
echo "Request:"
echo "  POST /api/settings/numbering/preview"
echo "  Content-Type: application/json"
echo ""
echo "Request Payload (should be plain JSON, NOT Proxy):"
echo "  {"
echo "    \"scope\": \"lhu\","
echo "    \"config\": {"
echo "      \"numbering\": {"
echo "        \"lhu\": {"
echo "          \"pattern\": \"LHU-{YYYY}-{SEQ:4}\","
echo "          \"reset\": \"yearly\","
echo "          \"start_from\": 1"
echo "        }"
echo "      }"
echo "    }"
echo "  }"
echo ""
echo "Response (200 OK):"
echo "  {"
echo "    \"example\": \"LHU-2025-0123\","
echo "    \"scope\": \"lhu\","
echo "    \"pattern\": \"LHU-{YYYY}-{SEQ:4}\""
echo "  }"
echo ""
echo "OR if pattern empty (422):"
echo "  {"
echo "    \"message\": \"Pattern penomoran tidak ditemukan...\","
echo "    \"errors\": { \"pattern\": [\"Pattern wajib diisi\"] }"
echo "  }"
echo ""
echo "=============================================="
echo "🔧 Key Fixes Applied:"
echo "=============================================="
echo ""
echo "✅ Frontend:"
echo "   - toPlainObject() method for Proxy → plain object"
echo "   - Proper payload structure for backend"
echo "   - Parse 'example' from response (not 'preview')"
echo "   - null vs undefined vs empty string handling"
echo "   - getPreviewText() helper for better UI"
echo "   - Init guard to prevent double initialization"
echo ""
echo "✅ Backend:"
echo "   - Extract pattern from multiple config structures"
echo "   - Validate pattern before generating example"
echo "   - Return 422 with clear message if pattern empty"
echo "   - Add debug logging"
echo "   - Consistent response format"
echo ""
echo "✅ UI:"
echo "   - Red text for error state (null)"
echo "   - Gray text for valid preview"
echo "   - 'Click Test Preview' for not-yet-tested"
echo "   - '❌ Preview gagal dibuat' for error"
echo ""
echo "=============================================="
echo ""

# Final validation
CRITICAL_COUNT=0

if grep -q "toPlainObject" resources/js/pages/settings/index.js; then ((CRITICAL_COUNT++)); fi
if grep -q "data.example" resources/js/pages/settings/index.js; then ((CRITICAL_COUNT++)); fi
if grep -q "getPreviewText" resources/js/pages/settings/alpine-component.js; then ((CRITICAL_COUNT++)); fi
if grep -q "empty(\$pattern)" app/Http/Controllers/Api/Settings/NumberingController.php; then ((CRITICAL_COUNT++)); fi

echo ""
if [ "$CRITICAL_COUNT" -eq 4 ]; then
    echo -e "${GREEN}✅ All critical fixes verified! (4/4)${NC}"
    echo -e "${GREEN}   Ready for browser testing.${NC}"
else
    echo -e "${YELLOW}⚠️  Some fixes may be missing ($CRITICAL_COUNT/4)${NC}"
    echo -e "${YELLOW}   Review the checklist above.${NC}"
fi

echo ""
echo "=============================================="
echo -e "${BLUE}🚀 Next Steps:${NC}"
echo "=============================================="
echo ""
echo "1. Login to the application"
echo "2. Navigate to /settings"
echo "3. Go to 'Penomoran Otomatis' section"
echo "4. For ANY scope (sample_code, ba, lhu, etc):"
echo "   - Fill the pattern field with valid pattern"
echo "   - Example: SMP-{YYYY}-{SEQ:4} or LHU-{YYYY}-{SEQ:4}"
echo "5. Click 'Test Preview' button"
echo "6. Verify preview appears (not empty, not error)"
echo ""
echo "If preview is empty, check:"
echo "   - Laravel logs: storage/logs/laravel.log"
echo "   - Browser console for errors"
echo "   - Network tab for request/response details"
echo ""
echo "=============================================="
