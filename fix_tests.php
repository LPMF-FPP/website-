<?php

// Fix RefreshDatabase to DatabaseTransactions in all test files

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

    // Replace import statement
    $content = str_replace(
        'use Illuminate\Foundation\Testing\RefreshDatabase;',
        'use Illuminate\Foundation\Testing\DatabaseTransactions;',
        $content
    );

    // Replace trait usage
    $content = str_replace(
        'use RefreshDatabase;',
        'use DatabaseTransactions;',
        $content
    );

    // Also handle combined traits like "use RefreshDatabase, WithoutMiddleware;"
    $content = str_replace(
        'use RefreshDatabase, ',
        'use DatabaseTransactions, ',
        $content
    );

    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        echo 'Fixed: '.$file->getPathname()."\n";
        $fixedCount++;
    }
}

echo "\nTotal files fixed: $fixedCount\n";
