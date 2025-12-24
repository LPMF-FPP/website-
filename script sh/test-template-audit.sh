#!/bin/bash
# Template Audit Logging - Test Script
# Verifies all audit logs are working correctly

set -e

echo "=========================================="
echo "Template Audit Logging Test"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

# Function to run SQL query
run_query() {
    local query=$1
    php artisan tinker --execute="DB::select(\"$query\");"
}

echo "1. Checking audit_logs table exists..."
if php artisan tinker --execute="Schema::hasTable('audit_logs');" | grep -q "true"; then
    echo -e "${GREEN}✓${NC} audit_logs table exists"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} audit_logs table NOT found"
    ((FAILED++))
fi
echo ""

echo "2. Checking AuditLog model..."
if php -r "require 'vendor/autoload.php'; class_exists('App\Models\AuditLog') && exit(0) || exit(1);"; then
    echo -e "${GREEN}✓${NC} AuditLog model exists"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} AuditLog model NOT found"
    ((FAILED++))
fi
echo ""

echo "3. Checking Audit helper..."
if php -r "require 'vendor/autoload.php'; class_exists('App\Support\Audit') && exit(0) || exit(1);"; then
    echo -e "${GREEN}✓${NC} Audit helper exists"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Audit helper NOT found"
    ((FAILED++))
fi
echo ""

echo "4. Checking DocumentTemplateController..."
if [ -f "app/Http/Controllers/Api/Settings/DocumentTemplateController.php" ]; then
    echo -e "${GREEN}✓${NC} DocumentTemplateController exists"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} DocumentTemplateController NOT found"
    ((FAILED++))
fi
echo ""

echo "5. Verifying audit log calls in controller..."
AUDIT_COUNT=$(grep -c "Audit::log" app/Http/Controllers/Api/Settings/DocumentTemplateController.php || echo "0")
if [ "$AUDIT_COUNT" -ge "10" ]; then
    echo -e "${GREEN}✓${NC} Found $AUDIT_COUNT Audit::log calls (expected 10+)"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Found only $AUDIT_COUNT Audit::log calls (expected 10+)"
    ((FAILED++))
fi
echo ""

echo "6. Verifying action names in controller..."
ACTIONS=(
    "TEMPLATE_CREATE"
    "TEMPLATE_UPDATE_DRAFT"
    "TEMPLATE_ISSUE_ACTIVATE"
    "TEMPLATE_DEACTIVATE"
    "TEMPLATE_PREVIEW"
    "TEMPLATE_UPLOAD"
    "TEMPLATE_DELETE"
)

for action in "${ACTIONS[@]}"; do
    if grep -q "$action" app/Http/Controllers/Api/Settings/DocumentTemplateController.php; then
        echo -e "  ${GREEN}✓${NC} $action"
        ((PASSED++))
    else
        echo -e "  ${RED}✗${NC} $action NOT found"
        ((FAILED++))
    fi
done
echo ""

echo "7. Verifying Gate::authorize calls..."
GATE_COUNT=$(grep -c "Gate::authorize('manage-settings')" app/Http/Controllers/Api/Settings/DocumentTemplateController.php || echo "0")
if [ "$GATE_COUNT" -ge "13" ]; then
    echo -e "${GREEN}✓${NC} Found $GATE_COUNT Gate::authorize calls (expected 13)"
    ((PASSED++))
else
    echo -e "${YELLOW}⚠${NC} Found $GATE_COUNT Gate::authorize calls (expected 13)"
    ((FAILED++))
fi
echo ""

echo "8. Checking syntax of controller..."
if php -l app/Http/Controllers/Api/Settings/DocumentTemplateController.php > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} PHP syntax is valid"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} PHP syntax error detected"
    ((FAILED++))
fi
echo ""

echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Create a test template via GrapesJS editor"
    echo "2. Check audit_logs table:"
    echo "   SELECT * FROM audit_logs WHERE action LIKE 'TEMPLATE_%' ORDER BY id DESC LIMIT 5;"
    echo "3. Verify before/after state is populated"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review.${NC}"
    exit 1
fi
