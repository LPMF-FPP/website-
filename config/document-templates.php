<?php

use App\Enums\DocumentRenderEngine;

return [
    'default_render_engine' => env('DOCUMENT_TEMPLATE_ENGINE', DocumentRenderEngine::DOMPDF->value),
    'preview_rate_limit' => (int) env('DOCUMENT_TEMPLATE_PREVIEW_RATE', 10),
    'allowed_asset_hosts' => array_filter(array_map('trim', explode(',', env('DOCUMENT_TEMPLATE_ALLOWED_HOSTS', '')))),
];
