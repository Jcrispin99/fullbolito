<?php

declare(strict_types=1);

return [
    'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    'currency' => strtoupper((string) env('MERCADOPAGO_CURRENCY', 'PEN')),
    'timeout' => (int) env('MERCADOPAGO_TIMEOUT', 15),
    'checkout_ttl_hours' => (int) env('MERCADOPAGO_CHECKOUT_TTL_HOURS', 24),
];
