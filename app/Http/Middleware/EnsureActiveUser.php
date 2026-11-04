<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && !$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'Akun dinonaktifkan. Hubungi Super Admin.']);
        }
        if ($user && !in_array($user->role, ['super_admin', 'admin_rt'], true)) {
            abort(403, 'Peran akun tidak diizinkan.');
        }
        return $next($request);
    }
}
