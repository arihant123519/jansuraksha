<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // FIX: property_exists() misses Eloquent dynamic attributes.
        // Check model type directly — only PoliceUser has a role column.
        if (!$user instanceof \App\Models\PoliceUser) {
            return response()->json(['message' => 'Police access only.'], 403);
        }

        if (!in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Insufficient role.'], 403);
        }

        return $next($request);
    }
}