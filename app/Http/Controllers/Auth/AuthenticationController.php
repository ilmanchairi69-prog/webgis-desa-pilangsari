<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class AuthenticationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user && $user->is_active && in_array($user->role, ['super_admin', 'admin_rt'], true)) {
                return redirect()->route('admin.dashboard');
            }

            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200', function (string $attribute, mixed $value, \Closure $fail): void {
                if (config('hashing.driver') === 'bcrypt' && strlen((string) $value) > 72) {
                    $fail('Email atau kata sandi tidak sesuai.');
                }
            }],
        ]);

        // Batas percobaan terpisah berdasarkan email+IP dan IP saja.
        // Ambang 5 kegagalan mengikuti pengujian keamanan login.
        $normalizedEmail = mb_strtolower(trim($credentials['email']));
        $credentials['email'] = $normalizedEmail;
        $ip = (string) $request->ip();
        $emailKey = 'admin-login:'.sha1($normalizedEmail.'|'.$ip);
        $ipKey = 'admin-login-ip:'.sha1($ip);
        $maxAttempts = 5;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($emailKey, $maxAttempts)
            || RateLimiter::tooManyAttempts($ipKey, $maxAttempts)) {
            $seconds = max(
                RateLimiter::availableIn($emailKey),
                RateLimiter::availableIn($ipKey)
            );

            return back()
                ->withErrors(['email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik."])
                ->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($emailKey, $decaySeconds);
            RateLimiter::hit($ipKey, $decaySeconds);

            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        if (! $user->is_active || ! in_array($user->role, ['super_admin', 'admin_rt'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun tidak aktif atau tidak mempunyai peran yang diizinkan.']);
        }
        try {
            DB::transaction(function () use ($user): void {
                $user->forceFill(['last_login_at' => now()])->save();
                AuditWriter::write('auth.login', $user, [], ['email' => $user->email]);
            });
        } catch (Throwable $exception) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw $exception;
        }
        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            AuditWriter::write('auth.logout', $request->user());
        } finally {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('home')->with('success', 'Anda telah keluar dari sistem.');
    }
}
