<?php

// Fix RefreshDatabase in Pest-style tests (uses() function)

$testsDir = __DIR__.'/tests';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir)
);

$fixedCount = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    $original = $content;

    // Replace Pest-style uses(RefreshDatabase::class)
    $content = str_replace(
        'uses(RefreshDatabase::class)',
        'uses(DatabaseTransactions::class)',
        $content
    );

    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        echo 'Fixed: '.$file->getPathname()."\n";
        $fixedCount++;
    }
}

echo "\nTotal files fixed: $fixedCount\n";
