#!/bin/bash
# Test script to verify container fix for Template Dokumen section

echo "🧪 Testing Container Not Found Fix"
echo "===================================="
echo ""

# Check 1: Section activation in startNewEditorTemplate
echo "✓ Check 1: startNewEditorTemplate has section activation"
if grep -q "activeSection !== 'templates'" resources/js/pages/settings/alpine-component.js && \
   grep -A3 "activeSection !== 'templates'" resources/js/pages/settings/alpine-component.js | grep -q "this.activeSection = 'templates'"; then
    echo "  ✅ PASS: Section activation logic found in startNewEditorTemplate"
else
    echo "  ❌ FAIL: Missing section activation in startNewEditorTemplate"
    exit 1
fi

echo ""

# Check 2: Section activation in loadTemplateDetail
echo "✓ Check 2: loadTemplateDetail has section activation"
if grep -A15 "async loadTemplateDetail" resources/js/pages/settings/alpine-component.js | \
   grep -q "activeSection !== 'templates'"; then
    echo "  ✅ PASS: Section activation logic found in loadTemplateDetail"
else
    echo "  ❌ FAIL: Missing section activation in loadTemplateDetail"
    exit 1
fi

echo ""

# Check 3: ensureTemplateEditor has guard clause
echo "✓ Check 3: ensureTemplateEditor has section guard"
if grep -A20 "async ensureTemplateEditor" resources/js/pages/settings/alpine-component.js | \
   grep -q "if (this.activeSection !== 'templates')"; then
    echo "  ✅ PASS: Section guard found in ensureTemplateEditor"
else
    echo "  ❌ FAIL: Missing section guard in ensureTemplateEditor"
    exit 1
fi

echo ""

# Check 4: Container fallback logic
echo "✓ Check 4: Container fallback to getElementById"
if grep -q "document.getElementById('gjs')" resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ PASS: Fallback to getElementById found"
else
    echo "  ❌ FAIL: Missing getElementById fallback"
    exit 1
fi

echo ""

# Check 5: $nextTick timing logic
echo "✓ Check 5: Proper timing with \$nextTick and setTimeout"
if grep -A2 "await this.\$nextTick();" resources/js/pages/settings/alpine-component.js | \
   grep -q "new Promise"; then
    echo "  ✅ PASS: Proper timing logic found"
else
    echo "  ❌ FAIL: Missing proper timing logic"
    exit 1
fi

echo ""
echo "===================================="
echo "✅ All checks passed!"
echo ""
echo "📝 Summary of fixes:"
echo "  1. startNewEditorTemplate() activates section before ensureTemplateEditor()"
echo "  2. loadTemplateDetail() activates section before ensureTemplateEditor()"
echo "  3. ensureTemplateEditor() has guard clause for inactive section"
echo "  4. Container lookup has fallback to document.getElementById()"
echo "  5. Proper timing with \$nextTick() + setTimeout() for DOM updates"
echo ""
echo "🎯 Expected behavior:"
echo "  - No 'Container element tidak ditemukan' errors"
echo "  - Section auto-activates when clicking '+ New Template'"
echo "  - Console shows '🔄 Activating templates section...'"
echo "  - Editor initializes successfully"
echo ""
echo "📖 See CONTAINER_FIX_SUMMARY.md for detailed documentation"
