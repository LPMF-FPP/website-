#!/bin/bash
# Verify GrapesJS container initialization fix

set -e

echo "🔍 Verifying GrapesJS Container Fix..."
echo ""

# Check 1: Container has min-height in Blade
echo "✓ Check 1: Container dimensions in Blade..."
if grep -q 'style="min-height: 75vh; width: 100%;"' resources/views/settings/partials/templates.blade.php; then
    echo "  ✅ Container has min-height: 75vh"
else
    echo "  ❌ Container missing min-height style"
    exit 1
fi

# Check 2: Retry loop in openTemplateEditorModal
echo ""
echo "✓ Check 2: Visibility retry loop in openTemplateEditorModal..."
if grep -q 'while (container.offsetParent === null && attempts < 20)' resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ Retry loop present (20 attempts × 50ms)"
else
    echo "  ❌ Retry loop missing"
    exit 1
fi

# Check 3: Fallback getElementById
echo ""
echo "✓ Check 3: Fallback container selector..."
if grep -q "container = document.getElementById('gjs-modal-editor')" resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ Fallback getElementById present"
else
    echo "  ❌ Fallback selector missing"
    exit 1
fi

# Check 4: Editor refresh after load
echo ""
echo "✓ Check 4: Editor refresh after initialization..."
if grep -q 'refreshTemplateEditor.*modal' resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ Editor refresh called"
else
    echo "  ❌ Editor refresh missing"
    exit 1
fi

# Check 5: Container validation in template-editor.js
echo ""
echo "✓ Check 5: Container validation in template-editor.js..."
if grep -q 'if (!container || container.offsetParent === null)' resources/js/pages/settings/template-editor.js; then
    echo "  ✅ Container validation present"
else
    echo "  ❌ Container validation missing"
    exit 1
fi

# Check 6: Build artifacts exist
echo ""
echo "✓ Check 6: Build artifacts..."
if [ -f public/build/manifest.json ]; then
    echo "  ✅ Vite build manifest exists"
    
    # Check for GrapesJS bundle
    if grep -q 'grapes.*\.js' public/build/manifest.json; then
        echo "  ✅ GrapesJS bundle found in manifest"
    else
        echo "  ⚠️  GrapesJS bundle not found (run npm run build)"
    fi
else
    echo "  ⚠️  Build manifest not found (run npm run build)"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ All checks passed!"
echo ""
echo "Manual Testing Steps:"
echo "1. Run: php artisan serve"
echo "2. Navigate to: http://localhost:8000/settings"
echo "3. Click 'Edit' on any template"
echo "4. Verify:"
echo "   • Modal opens without error"
echo "   • GrapesJS editor canvas appears"
echo "   • Can drag blocks to canvas"
echo "   • Can edit text inline"
echo "   • Close and reopen works"
echo ""
echo "Expected console output:"
echo "  🎨 Initializing GrapesJS editor..."
echo ""
echo "Should NOT see:"
echo "  ❌ Container editor tidak ditemukan"
echo "  ❌ Container editor belum visible"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
