<?php

return [
    'legacy' => [
        'office_enabled' => (bool) env('QMH_OFFICE_ENABLED', false),
        'docx_enabled' => (bool) env('QMH_DOCX_ENABLED', false),
    ],
];
