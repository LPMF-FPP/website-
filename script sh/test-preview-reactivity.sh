#!/bin/bash

# Test Preview Reactivity Fix - Validation Script
# Tests Alpine reactivity for numbering preview

echo "🧪 Testing Preview Reactivity Fix"
echo "=============================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "1️⃣ Checking Critical Code Patterns..."

# Check for object spread in testPreview (reactivity fix)
if grep -q "this.state.numberingPreview = {" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} Object spread pattern found (Alpine reactivity trigger)"
else
    echo -e "   ${RED}❌${NC} Object spread pattern not found - Alpine may not detect changes"
fi

# Check for ?? operator (nullish coalescing)
if grep -q "?? 'Click Test Preview'" resources/views/settings/partials/numbering.blade.php; then
    echo -e "   ${GREEN}✅${NC} Nullish coalescing operator (??) used in template"
else
    echo -e "   ${YELLOW}⚠️${NC}  Using || operator - may have false-negative with empty strings"
fi

# Check for proper bracket notation
if grep -q "\[scope\]:" resources/js/pages/settings/index.js; then
    echo -e "   ${GREEN}✅${NC} Bracket notation [scope] found for dynamic property"
else
    echo -e "   ${YELLOW}⚠️${NC}  Check if dynamic property access is correct"
fi

# Check for comprehensive logging
LOG_COUNT=$(grep -c "console.log" resources/js/pages/settings/index.js)
if [ "$LOG_COUNT" -gt 5 ]; then
    echo -e "   ${GREEN}✅${NC} Debug logging present ($LOG_COUNT console.log statements)"
else
    echo -e "   ${YELLOW}⚠️${NC}  Limited debug logging ($LOG_COUNT statements)"
fi

echo ""
echo "2️⃣ Validating State Initialization..."

# Check if all scopes are initialized
SCOPES=("sample_code" "ba" "lhu" "ba_penyerahan" "tracking")
INIT_LINE=$(grep -n "numberingPreview:" resources/js/pages/settings/index.js | head -1 | cut -d: -f1)

if [ -n "$INIT_LINE" ]; then
    INIT_CONTENT=$(sed -n "${INIT_LINE}p" resources/js/pages/settings/index.js)
    
    ALL_FOUND=true
    for scope in "${SCOPES[@]}"; do
        if echo "$INIT_CONTENT" | grep -q "$scope"; then
            echo -e "   ${GREEN}✅${NC} Scope '$scope' initialized"
        else
            echo -e "   ${RED}❌${NC} Scope '$scope' NOT initialized"
            ALL_FOUND=false
        fi
    done
    
    if $ALL_FOUND; then
        echo -e "   ${GREEN}✅${NC} All scopes properly initialized"
    fi
else
    echo -e "   ${RED}❌${NC} numberingPreview initialization not found"
fi

echo ""
echo "3️⃣ Checking Alpine Wrapper Methods..."

if grep -q "testPreview(scope)" resources/js/pages/settings/alpine-component.js; then
    echo -e "   ${GREEN}✅${NC} testPreview wrapper method exists"
else
    echo -e "   ${RED}❌${NC} testPreview wrapper method not found"
fi

if grep -q "@click.prevent=\"testPreview(scope)\"" resources/views/settings/partials/numbering.blade.php; then
    echo -e "   ${GREEN}✅${NC} Template calls testPreview(scope) correctly"
else
    echo -e "   ${YELLOW}⚠️${NC}  Check template button binding"
fi

echo ""
echo "4️⃣ Validating Build Output..."

if [ -f "public/build/manifest.json" ]; then
    BUILD_TIME=$(stat -c %y public/build/manifest.json 2>/dev/null || stat -f %Sm public/build/manifest.json 2>/dev/null)
    echo -e "   ${GREEN}✅${NC} Vite build exists (built: ${BUILD_TIME:0:19})"
    
    # Check if index.js is in manifest
    if grep -q "index.js" public/build/manifest.json; then
        echo -e "   ${GREEN}✅${NC} settings/index.js in build manifest"
    fi
else
    echo -e "   ${RED}❌${NC} Build manifest not found - run: npm run build"
fi

echo ""
echo "=============================================="
echo "📋 Manual Testing Checklist:"
echo "=============================================="
echo ""
echo "1. Open browser to: http://localhost:8000/settings"
echo "2. Open DevTools Console (F12)"
echo "3. Click 'Test Preview' on any scope (e.g., sample_code)"
echo ""
echo "Expected Console Output (in order):"
echo "  🔍 [Alpine Wrapper] testPreview called { scope: 'sample_code' }"
echo "  📊 Current preview state: { sample_code: '', ba: '', ... }"
echo "  ⚙️ Current form config: { pattern: '...', reset: '...' }"
echo "  ▶️ testPreview promise initiated for scope: sample_code"
echo "  🔍 [testPreview] Starting preview for scope: sample_code"
echo "  → POST /api/settings/numbering/preview { scope, config }"
echo "  ✓ Preview response: { data, extractedValue: 'SMP-2025-0001' }"
echo "  ✓ State updated: { scope, value: 'SMP-2025-0001', fullState: {...} }"
echo ""
echo "Expected UI Behavior:"
echo "  ✅ Button text changes: 'Test Preview' → 'Testing...' → 'Test Preview'"
echo "  ✅ Preview box shows: 'Click Test Preview' → 'SMP-2025-0001' (or similar)"
echo "  ✅ Success message appears: 'Preview berhasil!' (green)"
echo "  ✅ No Alpine errors in console"
echo ""
echo "Expected Network Tab:"
echo "  ✅ POST /api/settings/numbering/preview"
echo "  ✅ Request payload: { scope: 'sample_code', pattern: '...', ... }"
echo "  ✅ Response 200 OK with { preview: '...' }"
echo ""
echo "=============================================="
echo "🔍 Key Fixes Applied:"
echo "=============================================="
echo ""
echo "✅ Object spread for Alpine reactivity:"
echo "   this.state.numberingPreview = { ...this.state.numberingPreview, [scope]: value }"
echo ""
echo "✅ Nullish coalescing for fallback:"
echo "   x-text=\"client.state.numberingPreview?.[scope] ?? 'Click Test Preview'\""
echo ""
echo "✅ Comprehensive console logging:"
echo "   - Alpine wrapper call"
echo "   - State snapshots"
echo "   - Request/response tracking"
echo "   - State update confirmation"
echo ""
echo "✅ Proper initialization:"
echo "   - All scopes pre-initialized as empty strings"
echo "   - previewLoading object for each scope"
echo "   - previewState tracking"
echo ""
echo "=============================================="

# Final check
CRITICAL_FIXES=0
if grep -q "this.state.numberingPreview = {" resources/js/pages/settings/index.js; then
    ((CRITICAL_FIXES++))
fi
if grep -q "?? 'Click Test Preview'" resources/views/settings/partials/numbering.blade.php; then
    ((CRITICAL_FIXES++))
fi

echo ""
if [ "$CRITICAL_FIXES" -eq 2 ]; then
    echo -e "${GREEN}✅ All critical fixes verified! Ready for testing.${NC}"
else
    echo -e "${YELLOW}⚠️  Some fixes may be missing. Review the checklist above.${NC}"
fi

echo "=============================================="
