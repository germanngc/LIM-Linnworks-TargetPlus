<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) ($request->header('X-Correlation-ID') ?: Str::uuid());
        Context::add('correlation_id', $id);
        Log::withContext(['correlation_id' => $id]);
        $request->attributes->set('correlation_id', $id);
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);
        return $response;
    }
}
