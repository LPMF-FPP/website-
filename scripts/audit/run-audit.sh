#!/bin/bash
###############################################################################
# Frontend Audit Runner
# Quick helper script for running audits with common options
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

check_server() {
    if ! curl -s http://127.0.0.1:8000 > /dev/null 2>&1; then
        print_warning "Laravel server not running at http://127.0.0.1:8000"
        echo "  Some audits (a11y, coverage, lighthouse) require the server."
        echo "  Start with: php artisan serve"
        return 1
    fi
    return 0
}

# Main
print_header "Frontend Audit System"

echo "Select audit to run:"
echo ""
echo "  1) Critical checks only (guard + cascade + contrast)"
echo "  2) All audits (comprehensive)"
echo "  3) CSS audits (stylelint + cascade + guard + contrast + zindex)"
echo "  4) JS audits (eslint)"
echo "  5) Accessibility (a11y)"
echo "  6) Performance (coverage + lighthouse)"
echo "  7) Custom selection"
echo "  8) Exit"
echo ""
read -p "Enter choice [1-8]: " choice

case $choice in
    1)
        print_header "Running Critical Audits"
        npm run audit:critical
        ;;
    2)
        print_header "Running All Audits"
        check_server
        npm run audit:all
        ;;
    3)
        print_header "Running CSS Audits"
        npm run audit:stylelint
        npm run audit:cascade
        npm run audit:guard
        npm run audit:contrast
        npm run audit:zindex
        ;;
    4)
        print_header "Running JS Audits"
        npm run audit:eslint
        ;;
    5)
        print_header "Running Accessibility Audit"
        if check_server; then
            npm run audit:a11y
        else
            exit 1
        fi
        ;;
    6)
        print_header "Running Performance Audits"
        if check_server; then
            npm run audit:coverage
            npm run audit:lh
        else
            exit 1
        fi
        ;;
    7)
        print_header "Custom Audit Selection"
        echo "Available audits:"
        echo "  - stylelint"
        echo "  - eslint"
        echo "  - cascade"
        echo "  - guard"
        echo "  - contrast"
        echo "  - zindex"
        echo "  - a11y (requires server)"
        echo "  - coverage (requires server)"
        echo "  - lh (requires server)"
        echo ""
        read -p "Enter audit names (space-separated): " audits
        for audit in $audits; do
            print_header "Running: audit:$audit"
            npm run "audit:$audit"
        done
        ;;
    8)
        echo "Goodbye!"
        exit 0
        ;;
    *)
        print_error "Invalid choice"
        exit 1
        ;;
esac

print_header "Audit Complete"
echo "Reports saved to: report/"
echo ""
echo "View reports:"
echo "  cat report/cascade-map.md"
echo "  cat report/nonlayout-violations.md"
echo "  cat report/contrast.md"
echo ""
print_success "Done!"
