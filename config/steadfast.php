<?php

return [
    'api_key' => env('STEADFAST_API_KEY'),
    'secret_key' => env('STEADFAST_SECRET_KEY'),
    'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),
    'timeout' => (int) env('STEADFAST_TIMEOUT', 30),
    'connect_timeout' => (int) env('STEADFAST_CONNECT_TIMEOUT', 10),
    'invoice_prefix' => env('STEADFAST_INVOICE_PREFIX', 'ORD'),
];
