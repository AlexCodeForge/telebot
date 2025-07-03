<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     * Simple admin check - only user with ID 1 is admin
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated and has ID 1 (the only admin)
        if (!Auth::check() || Auth::id() !== 1) {
            // Redirect non-admin users to dashboard
            return redirect('/dashboard')->with('error', 'Access denied. Admin privileges required.');
        }

        return $next($request);
    }
}
