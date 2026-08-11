<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', implode(',', [
        'localhost', 'localhost:3000', '127.0.0.1', '127.0.0.1:3000', '::1',
    ]))),
    // Token-only API: there is no session/cookie "web" guard, and BOTH the
    // 'api' and 'police' guards use the Sanctum driver. Listing a Sanctum-driver
    // guard here makes Sanctum's guard re-invoke itself → infinite recursion →
    // PHP stack overflow / worker crash on every authenticated request. An empty
    // list skips the stateful pre-check and goes straight to Bearer-token auth.
    'guard'        => [],
    'expiration'   => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware'   => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];