<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Simple admin access check - only allow admin@telebot.local
     */
    protected function requireAdmin()
    {
        $user = Auth::user();
        if (!$user || !$user->is_admin || $user->email !== 'admin@telebot.local') {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Access denied. Admin privileges required.');
        }
        return null; // No redirect needed
    }
}
