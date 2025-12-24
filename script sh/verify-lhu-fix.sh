#!/bin/bash
# Manual test script to verify LHU numbering fix

set -e

echo "=== Manual LHU Numbering Fix Verification ==="
echo ""
echo "Step 1: Check current LHU numbering settings"
php artisan tinker --execute='echo "Current LHU pattern: " . settings("numbering.lhu.pattern") . PHP_EOL;'

echo ""
echo "Step 2: Find a sample process (or create one if needed)"
php artisan tinker --execute='
$process = App\Models\SampleTestProcess::with("sample.testRequest")->where("stage", "interpretation")->first();
if ($process) {
    echo "Found process ID: " . $process->id . PHP_EOL;
    echo "Sample: " . ($process->sample->sample_code ?? "N/A") . PHP_EOL;
    echo "Current metadata: " . json_encode($process->metadata ?? []) . PHP_EOL;
} else {
    echo "No processes found in interpretation stage" . PHP_EOL;
}
'

echo ""
echo "Step 3: To test, visit in browser:"
echo "1. Go to /settings and configure LHU numbering pattern (e.g., 'FIXED-{YYYY}-{NNNN}')"
echo "2. Go to /sample-processes/3 (or the ID from step 2)"
echo "3. Click 'Generate LHU' button"
echo "4. Check that the PDF uses the pattern you configured in step 1"
echo ""
echo "Expected behavior:"
echo "- First generation: issues a new number using current settings, stores it in metadata"
echo "- Regeneration: reuses the stored number (doesn't change)"
echo "- Different process: uses latest settings for its first generation"
echo ""
echo "=== Verification Complete ==="
