<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING LOGO PATHS ===\n\n";

$logoTribrata = public_path('images/logo-tribrata-polri.png');
$logoPusdokkes = public_path('images/logo-pusdokkes-polri.png');

echo "Logo Tribrata Path:\n";
echo "  Path: $logoTribrata\n";
echo "  Exists: " . (file_exists($logoTribrata) ? "YES" : "NO") . "\n";
if (file_exists($logoTribrata)) {
    echo "  Size: " . number_format(filesize($logoTribrata)) . " bytes\n";
}
echo "\n";

echo "Logo Pusdokkes Path:\n";
echo "  Path: $logoPusdokkes\n";
echo "  Exists: " . (file_exists($logoPusdokkes) ? "YES" : "NO") . "\n";
if (file_exists($logoPusdokkes)) {
    echo "  Size: " . number_format(filesize($logoPusdokkes)) . " bytes\n";
}
echo "\n";

// Test Python script dengan logo paths
echo "=== TESTING PYTHON SCRIPT ===\n\n";

$command = [
    'python',
    __DIR__ . '/scripts/generate_berita_acara.py',
    '--help'
];

$process = new \Symfony\Component\Process\Process($command);
$process->run();

echo $process->getOutput();

if (!$process->isSuccessful()) {
    echo "Error:\n";
    echo $process->getErrorOutput();
}
