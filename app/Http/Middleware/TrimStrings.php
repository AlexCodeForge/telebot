<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;
use Illuminate\Http\Request;

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
     * Override to add serverless-friendly handling for JSON requests.
     */
    public function handle($request, \Closure $next)
    {
        // Skip trimming for JSON requests to avoid FormData processing issues in serverless
        if ($request->isJson() || $request->getContentType() === 'json') {
            return $next($request);
        }

        // Skip trimming for direct upload endpoints that handle raw binary data
        if ($request->is('admin/videos/direct-upload')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
