<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Closure;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Handle an incoming request.
     * Enhanced serverless-friendly handling for various request types.
     */
    public function handle($request, Closure $next)
    {
        // Skip trimming for JSON requests to avoid FormData processing issues in serverless
        if ($request->isJson() || $request->getContentType() === 'json') {
            return $next($request);
        }

        // Skip trimming for multipart requests that might have large payloads
        if ($request->isMethod('POST') && str_contains($request->header('Content-Type', ''), 'multipart/form-data')) {
            return $next($request);
        }

        // Skip trimming for direct upload endpoints that handle raw binary data
        if ($request->is('admin/videos/direct-upload') || $request->is('admin/videos/*/')) {
            return $next($request);
        }

        // Skip trimming for file upload requests to prevent serverless timeout
        if ($request->hasFile('thumbnail') || $request->has('_method')) {
            return $next($request);
        }

        // Add memory limit check for serverless environment
        try {
            // Only proceed with trimming for small, simple requests
            if ($this->isLargeRequest($request)) {
                return $next($request);
            }

            return parent::handle($request, $next);
        } catch (\Throwable $e) {
            // If trimming fails, proceed without trimming (serverless fallback)
            Log::warning('TrimStrings middleware failed, proceeding without trimming', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
                'method' => $request->method()
            ]);

            return $next($request);
        }
    }

    /**
     * Check if request is too large for serverless processing
     */
    private function isLargeRequest(Request $request): bool
    {
        // Skip trimming if request content is large (>1MB)
        $contentLength = $request->header('Content-Length', 0);
        if ($contentLength > 1024 * 1024) {
            return true;
        }

        // Skip if request has many parameters (potential large FormData)
        if (count($request->all()) > 50) {
            return true;
        }

        return false;
    }
}
