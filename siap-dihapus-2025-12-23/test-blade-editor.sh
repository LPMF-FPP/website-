#!/bin/bash

echo "========================================="
echo "Blade Template Editor - Quick Test"
echo "========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Laravel is accessible
echo "1. Checking Laravel installation..."
if php artisan --version &> /dev/null; then
    echo -e "${GREEN}✓${NC} Laravel is accessible"
else
    echo -e "${RED}✗${NC} Cannot access Laravel artisan"
    exit 1
fi

# Check routes
echo ""
echo "2. Verifying routes..."
ROUTE_COUNT=$(php artisan route:list --path=blade-templates --json | grep -c "blade-templates")
if [ "$ROUTE_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Found $ROUTE_COUNT blade-template routes"
else
    echo -e "${RED}✗${NC} No blade-template routes found"
    exit 1
fi

# Check controller file exists
echo ""
echo "3. Checking controller file..."
if [ -f "app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php" ]; then
    echo -e "${GREEN}✓${NC} Controller file exists"
else
    echo -e "${RED}✗${NC} Controller file not found"
    exit 1
fi

# Check middleware file exists
echo ""
echo "4. Checking middleware file..."
if [ -f "app/Http/Middleware/ValidateBladeTemplateAccess.php" ]; then
    echo -e "${GREEN}✓${NC} Middleware file exists"
else
    echo -e "${RED}✗${NC} Middleware file not found"
    exit 1
fi

# Check view file exists
echo ""
echo "5. Checking view file..."
if [ -f "resources/views/settings/blade-templates.blade.php" ]; then
    echo -e "${GREEN}✓${NC} View file exists"
else
    echo -e "${RED}✗${NC} View file not found"
    exit 1
fi

# Check editable template files exist
echo ""
echo "6. Checking editable template files..."
TEMPLATES=(
    "resources/views/pdf/berita-acara-penerimaan.blade.php"
    "resources/views/pdf/ba-penyerahan.blade.php"
    "resources/views/pdf/laporan-hasil-uji.blade.php"
    "resources/views/pdf/form-preparation.blade.php"
)

MISSING=0
for template in "${TEMPLATES[@]}"; do
    if [ -f "$template" ]; then
        echo -e "${GREEN}✓${NC} $template"
    else
        echo -e "${RED}✗${NC} $template (missing)"
        MISSING=$((MISSING + 1))
    fi
done

if [ $MISSING -gt 0 ]; then
    echo -e "${YELLOW}⚠${NC} Warning: $MISSING template file(s) missing"
fi

# Check storage directory permissions
echo ""
echo "7. Checking storage permissions..."
if [ -w "storage/app" ]; then
    echo -e "${GREEN}✓${NC} storage/app is writable"
else
    echo -e "${YELLOW}⚠${NC} storage/app is not writable - backups may fail"
fi

# Create backup directory if it doesn't exist
echo ""
echo "8. Ensuring backup directory exists..."
mkdir -p storage/app/template-backups
if [ -d "storage/app/template-backups" ]; then
    echo -e "${GREEN}✓${NC} Backup directory created/exists"
else
    echo -e "${RED}✗${NC} Could not create backup directory"
fi

# Check resources/views/pdf permissions
echo ""
echo "9. Checking template directory permissions..."
if [ -w "resources/views/pdf" ]; then
    echo -e "${GREEN}✓${NC} resources/views/pdf is writable"
else
    echo -e "${YELLOW}⚠${NC} resources/views/pdf is not writable - template edits will fail"
    echo "   Run: chmod -R 775 resources/views/pdf"
fi

# Clear caches
echo ""
echo "10. Clearing caches..."
php artisan cache:clear &> /dev/null
php artisan view:clear &> /dev/null
echo -e "${GREEN}✓${NC} Caches cleared"

echo ""
echo "========================================="
echo -e "${GREEN}All checks completed!${NC}"
echo "========================================="
echo ""
echo "Next steps:"
echo "1. Ensure you have a user with 'manage-settings' permission"
echo "2. Visit: /settings/blade-templates"
echo "3. Select a template and start editing!"
echo ""
echo "Documentation:"
echo "- Full guide: BLADE_TEMPLATE_EDITOR.md"
echo "- Summary: BLADE_EDITOR_IMPLEMENTATION_SUMMARY.md"
echo ""
