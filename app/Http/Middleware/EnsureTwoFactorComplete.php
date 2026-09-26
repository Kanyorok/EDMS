<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent authenticated users who have 2FA enabled from accessing
 * the application before completing the TOTP challenge.
 *
 * The TOTP challenge itself lives under EnsurePendingTwoFactor (guest-equivalent)
 * and is intentionally NOT reachable from here — redirecting an authenticated user
 * to a guest route causes an infinite redirect loop. Instead, any invalid state
 * (authenticated + 2FA enrolled + no 2fa_confirmed) is resolved by forcing a clean
 * logout so the user can restart from the login page.
 */
class EnsureTwoFactorComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Skip if user has not enrolled 2FA
        if (! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        // Skip if already confirmed in this session
        if ($request->session()->get('2fa_confirmed')) {
            return $next($request);
        }

        // Allow logout and timeout routes to proceed without entering the loop
        if ($request->routeIs('logout', 'timeout')) {
            return $next($request);
        }

        // At this point: authenticated + 2FA enrolled + confirmation absent.
        // This is an invalid session state (session loss, partial write, or tampering).
        // The only safe resolution is to force a clean logout and require re-login.
        // DO NOT redirect to two-factor.challenge — that route guards against authenticated
        // users and will create an infinite redirect loop (guest middleware conflict).
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('error', 'Your session has expired or is incomplete. Please sign in again.');
    }
}
