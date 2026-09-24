<?php

return [
    'force_https' => (bool) env('APP_FORCE_HTTPS', false),
    'indexing' => (bool) env('SITE_INDEXING', false),
    'trusted_proxies' => env('TRUSTED_PROXIES') === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
