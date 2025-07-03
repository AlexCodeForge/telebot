<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServerlessTrimStrings
{
    /**
     * Handle an incoming request - optimized for serverless environments
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Skip trimming for JSON requests to avoid FormData issues
            if ($request->isJson() || $request->expectsJson()) {
                return $next($request);
            }

            // Skip trimming for large requests to avoid memory issues
            $contentLength = $request->header('Content-Length', 0);
            if ($contentLength > 5000000) { // 5MB limit
                Log::warning('Skipping trim for large request', ['size' => $contentLength]);
                return $next($request);
            }

            // Only trim if we have manageable form data
            if ($request->isMethod('POST') || $request->isMethod('PUT')) {
                $this->trimStrings($request);
            }

            return $next($request);
        } catch (\Exception $e) {
            Log::error('TrimStrings middleware failed', ['error' => $e->getMessage()]);
            // Continue without trimming if there's an error
            return $next($request);
        }
    }

    /**
     * Trim the strings in the request
     */
    protected function trimStrings(Request $request)
    {
        $input = $request->all();

        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        });

        $request->merge($input);
    }
}
