#!/bin/bash

# Settings Save Diagnostic Test
# Tests all sections of /settings page to find which ones actually save

echo "=== SETTINGS SAVE DIAGNOSTIC TEST ==="
echo ""

# Get auth token (assumes you're logged in as admin)
TOKEN=$(php artisan tinker --execute="echo \App\Models\User::where('role', 'admin')->first()->createToken('test')->plainTextToken;")

echo "Auth token: ${TOKEN:0:20}..."
echo ""

# Test 1: Branding
echo "TEST 1: Branding Settings"
curl -s -X PUT http://127.0.0.1:8000/api/settings/branding \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "branding": {
      "organization_name": "TEST_ORG_'$(date +%s)'"
    },
    "pdf": {}
  }' | jq '.branding.organization_name'

# Verify
sleep 1
curl -s http://127.0.0.1:8000/api/settings \
  -H "Authorization: Bearer $TOKEN" | jq '.settings.branding.organization_name'

echo ""

# Test 2: Localization
echo "TEST 2: Localization Settings"
curl -s -X PUT http://127.0.0.1:8000/api/settings/localization-retention \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "localization": {
      "timezone": "Asia/Jakarta"
    },
    "retention": {
      "storage_driver": "public",
      "storage_folder_path": "",
      "purge_after_days": 365
    }
  }' | jq '.localization.timezone'

# Verify
sleep 1
curl -s http://127.0.0.1:8000/api/settings \
  -H "Authorization: Bearer $TOKEN" | jq '.settings.localization.timezone'

echo ""
echo "=== END OF TEST ==="
