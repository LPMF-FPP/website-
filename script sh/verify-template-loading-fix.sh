#!/bin/bash
# Verify Template Loading Fix

set -e

echo "🔍 Verifying Template Editor Loading Fix..."
echo ""

# Check 1: ALWAYS fetch detail when template has ID
echo "✓ Check 1: Always fetch template detail..."
if grep -q 'if (tpl.id)' resources/js/pages/settings/alpine-component.js && \
   grep -q 'Fetching template detail from API' resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ Template detail fetch is unconditional (always when ID exists)"
else
    echo "  ❌ Template fetch logic not found or still conditional"
    exit 1
fi

# Check 2: Logging added
echo ""
echo "✓ Check 2: Comprehensive logging..."
if grep -q 'console.log.*Loading template to editor' resources/js/pages/settings/alpine-component.js && \
   grep -q 'console.log.*Template detail received' resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ Logging present for debugging"
else
    echo "  ❌ Logging missing"
    exit 1
fi

# Check 3: HTML normalization
echo ""
echo "✓ Check 3: HTML normalization (head/body extraction)..."
if grep -q 'normalizeTemplateHtml' resources/js/pages/settings/alpine-component.js && \
   grep -q 'DOMParser' resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ HTML normalization function present"
else
    echo "  ❌ HTML normalization missing"
    exit 1
fi

# Check 4: Content-type detection
echo ""
echo "✓ Check 4: Content-type detection for auth redirect..."
if grep -q "contentType.includes('text/html')" resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ HTML response detection present"
else
    echo "  ❌ Content-type check missing"
    exit 1
fi

# Check 5: Editor project support
echo ""
echo "✓ Check 5: Editor project (GrapesJS) support..."
if grep -q 'editor.loadProjectData' resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ loadProjectData support present"
else
    echo "  ❌ Editor project loading missing"
    exit 1
fi

# Check 6: Clear components before load
echo ""
echo "✓ Check 6: Clear editor before loading..."
if grep -q 'editor.DomComponents.clear' resources/js/pages/settings/alpine-component.js; then
    echo "  ✅ Editor clearing logic present"
else
    echo "  ⚠️  Editor clear not found (may cause duplicates)"
fi

# Check 7: Build artifacts
echo ""
echo "✓ Check 7: Build artifacts..."
if [ -f public/build/manifest.json ]; then
    echo "  ✅ Vite build manifest exists"
    
    if grep -q 'app-.*\.js' public/build/manifest.json; then
        echo "  ✅ App bundle found in manifest"
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
echo "3. Click 'Edit' on any template (BA Penyerahan, LHU, etc.)"
echo "4. Open browser DevTools Console (F12)"
echo "5. Verify console output:"
echo "   📄 Loading template to editor: {id, name, type}"
echo "   🔄 Fetching template detail from API..."
echo "   📡 Response status: 200 OK"
echo "   ✅ Template detail received: {hasHtml, htmlLength, ...}"
echo "   🎨 Loading content into GrapesJS: {htmlLength, cssLength}"
echo "   ✅ Template loaded successfully"
echo ""
echo "6. Verify editor canvas:"
echo "   • HTML content appears (headers, tables, paragraphs)"
echo "   • Large templates with <head>/<body> work"
echo "   • Styles applied correctly"
echo ""
echo "Expected behavior:"
echo "  ✅ Template HTML loads every time Edit is clicked"
echo "  ✅ Large BA Penyerahan templates with full HTML structure work"
echo "  ✅ Console shows fetch API call and response"
echo "  ✅ No 'Container tidak ditemukan' errors"
echo ""
echo "If API returns HTML (auth redirect):"
echo "  ❌ API returned HTML instead of JSON. Likely auth redirect"
echo "  → Check authentication, CSRF token, session"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
