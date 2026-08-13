<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAuthorizationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('AuthorizationToken');

        if (!$token) {
            return response()->json(['Error' => 'Missing AuthorizationToken'], 401);
        }

        $tenant = Tenant::where('authorization_token', $token)
            ->where('active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['Error' => 'Invalid or inactive AuthorizationToken'], 401);
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}

