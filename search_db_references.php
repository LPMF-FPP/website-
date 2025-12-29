<?php

use App\Models\InventoryMovement;
use App\Models\Document;
use App\Models\SystemSetting;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Searching for 'regulation', 'guideline', 'sop' in DB...\n";

// Check InventoryMovement reference_type
if (Schema::hasTable('inventory_movements')) {
    echo "Checking InventoryMovement distinct reference_type:\n";
    $types = InventoryMovement::distinct()->pluck('reference_type');
    foreach ($types as $t) {
        echo " - " . $t . "\n";
    }
}

// Check Document document_type
if (Schema::hasTable('documents')) {
    echo "Checking Document distinct document_type:\n";
    $types = Document::distinct()->pluck('document_type');
    foreach ($types as $t) {
        echo " - " . $t . "\n";
    }
}

// Check SystemSetting keys and values
echo "Checking SystemSettings:\n";
$settings = SystemSetting::all();
foreach ($settings as $s) {
    $json = json_encode($s->value);
    if (stripos($json, 'regulation') !== false || stripos($json, 'sop') !== false || stripos($json, 'guideline') !== false) {
        echo "Found in SystemSetting [{$s->key}]: $json\n";
    }
}

// Check if there is a 'references' table
if (Schema::hasTable('references')) {
    echo "Found 'references' table!\n";
    $refs = DB::table('references')->get();
    foreach ($refs as $r) {
        echo " - " . json_encode($r) . "\n";
    }
} else {
    echo "No 'references' table found.\n";
}

// Check if there is a 'reference_types' table
if (Schema::hasTable('reference_types')) {
    echo "Found 'reference_types' table!\n";
    $refs = DB::table('reference_types')->get();
    foreach ($refs as $r) {
        echo " - " . json_encode($r) . "\n";
    }
} else {
    echo "No 'reference_types' table found.\n";
}
