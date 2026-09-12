<?php

return [
    // Diisi di .env server setelah app Shopee disetujui.
    'partner_id'  => env('SHOPEE_PARTNER_ID'),
    'partner_key' => env('SHOPEE_PARTNER_KEY'),

    // URL callback OAuth — harus didaftarkan di Shopee Console.
    'redirect_url' => env('SHOPEE_REDIRECT_URL', rtrim((string) env('APP_URL'), '/') . '/api/v1/shopee/callback'),

    // Sandbox (test) atau live.
    'sandbox' => filter_var(env('SHOPEE_SANDBOX', true), FILTER_VALIDATE_BOOL),

    'base_url_live'    => 'https://partner.shopeemobile.com',
    'base_url_sandbox' => 'https://partner.test-stable.shopeemobile.com',
];
