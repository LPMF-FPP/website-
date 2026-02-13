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
];
