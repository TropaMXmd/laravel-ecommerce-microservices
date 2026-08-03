<?php

return [
    'enabled'      => env('HTTP_LOG_ENABLED', true),
    'level'        => env('HTTP_LOG_LEVEL', 'all'),      // all | errors_only | none
    'log_outgoing' => env('HTTP_LOG_OUTGOING', true),

    // Routes to skip — health checks flood the table otherwise
    'skip_paths'   => [
        'api/*/health',
        'api/v1/health',
    ],
];