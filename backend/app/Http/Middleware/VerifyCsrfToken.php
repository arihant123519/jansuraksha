<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * API routes use Sanctum token auth — no CSRF needed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
    ];
}