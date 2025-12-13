<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Output Path
    |--------------------------------------------------------------------------
    |
    | This value determines the base path where generated documents are stored.
    | By default, it's set to the 'output' directory relative to the base path.
    |
    */
    'output_path' => env('DOCUMENT_OUTPUT_PATH', 'output'),

    /*
    |--------------------------------------------------------------------------
    | Document Type Paths
    |--------------------------------------------------------------------------
    |
    | These values define the subdirectories for different document types.
    |
    */
    'ba_penyerahan' => [
        'path' => 'BA_Penyerahan_Ringkasan',
        'label' => 'Berita Acara Penyerahan',
    ],

    'lhu' => [
        'path' => 'laporan-hasil-uji/Laporan_Hasil_Uji',
        'label' => 'Laporan Hasil Uji',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for generated documents scanning.
    |
    */
    'cache' => [
        'enabled' => env('DOCUMENT_CACHE_ENABLED', true),
        'ttl' => env('DOCUMENT_CACHE_TTL', 600), // 10 minutes in seconds
    ],
];
