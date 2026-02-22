<?php

return [
    'legacy' => [
        'office_enabled' => (bool) env('QMH_OFFICE_ENABLED', false),
        'docx_enabled' => (bool) env('QMH_DOCX_ENABLED', false),
    ],
    'fr_v2' => [
        'enabled' => (bool) env('QMH_FR_V2_ENABLED', true),
        'create_enabled' => (bool) env('QMH_FR_V2_CREATE_ENABLED', false),
        'source_pdf_disk' => (string) env('QMH_FR_V2_SOURCE_PDF_DISK', 'local'),
        'source_pdf_dir' => (string) env('QMH_FR_V2_SOURCE_PDF_DIR', 'qmh/fr-v2/source-pdf'),
        'max_pdf_size_kb' => (int) env('QMH_FR_V2_MAX_PDF_SIZE_KB', 10240),
        'max_pdf_pages' => (int) env('QMH_FR_V2_MAX_PDF_PAGES', 40),
        'checker_timeout_seconds' => (int) env('QMH_FR_V2_CHECKER_TIMEOUT_SECONDS', 10),
        'checker_timeout_mode' => (string) env('QMH_FR_V2_CHECKER_TIMEOUT_MODE', 'unavailable'),
        'preview_temp_dir' => (string) env('QMH_FR_V2_PREVIEW_TEMP_DIR', 'qmh/fr-v2/preview-temp'),
        'preview_temp_ttl_minutes' => (int) env('QMH_FR_V2_PREVIEW_TEMP_TTL_MINUTES', 120),
        'docx_cleanup_enabled' => (bool) env('QMH_FR_V2_DOCX_CLEANUP_ENABLED', false),
    ],
    'pendukung' => [
        'storage_disk' => (string) env('QMH_PENDUKUNG_STORAGE_DISK', 'local'),
        'storage_dir' => (string) env('QMH_PENDUKUNG_STORAGE_DIR', 'qmh-pendukung'),
        'max_file_size_kb' => (int) env('QMH_PENDUKUNG_MAX_FILE_SIZE_KB', 30720),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ],
    ],
    'rapat_attachments' => [
        'storage_disk' => (string) env('QMH_RAPAT_ATTACHMENT_DISK', 'local'),
        'storage_dir' => (string) env('QMH_RAPAT_ATTACHMENT_DIR', 'qmh-rapat-attachments'),
        'max_file_size_kb' => (int) env('QMH_RAPAT_ATTACHMENT_MAX_FILE_SIZE_KB', 15360),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ],
    ],
];
