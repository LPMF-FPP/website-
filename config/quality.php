<?php

return [
    'office' => [
        'editor_url' => env('QMH_OFFICE_EDITOR_URL', ''),
        'jwt_secret' => env('QMH_OFFICE_JWT_SECRET', env('APP_KEY', 'qmh-office-secret')),
        'token_ttl_seconds' => (int) env('QMH_OFFICE_TOKEN_TTL_SECONDS', 3600),
        'callback_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', (string) env('QMH_OFFICE_CALLBACK_HOSTS', ''))
        ))),
        'callback_host_header' => env('QMH_OFFICE_CALLBACK_HOST_HEADER', 'X-Office-Callback-Host'),
    ],

    'export' => [
        'docx_to_pdf' => [
            'enabled' => (bool) env('QMH_DOCX_TO_PDF_ENABLED', false),
            'soffice_binary' => env('QMH_SOFFICE_BINARY', 'soffice'),
            'qpdf_binary' => env('QMH_QPDF_BINARY', 'qpdf'),
            'timeout_seconds' => (int) env('QMH_DOCX_TO_PDF_TIMEOUT_SECONDS', 90),
        ],
    ],
];
