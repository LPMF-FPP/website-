<?php

return [
    // Default: auth enforced on routes via middleware. Policy enforcement toggled via env.
    'enforce_search_policy' => env('SEARCH_ENFORCE_POLICY', false),
    'enforce_download_policy' => env('SEARCH_ENFORCE_DOWNLOAD_POLICY', false),

    // Allowed document types (lowercase). "all" disables filtering.
    'doc_types' => ['all', 'sample_photo', 'ba_penerimaan', 'ba_penyerahan', 'request_letter', 'bap', 'ba'],
    'doc_type_labels' => [
        'all' => 'Semua Dokumen',
        'sample_photo' => 'Foto Sampel',
        'ba_penerimaan' => 'Berita Acara Penerimaan Sampel',
        'ba_penyerahan' => 'Berita Acara Penyerahan Sampel',
        'request_letter' => 'Surat Permintaan Pengujian',
        'bap' => 'Berita Acara Pemeriksaan',
        'ba' => 'Berita Acara',
    ],

    // Storage disks for document downloads and photos.
    'documents_disk' => env('SEARCH_DOCUMENTS_DISK', 'documents'),
    'photos_disk' => env('SEARCH_PHOTOS_DISK', 'public'),

    // Maximum related cases returned for a person in search results.
    'people_cases_limit' => 5,
];
