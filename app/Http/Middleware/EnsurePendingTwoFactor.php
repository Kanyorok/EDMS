<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard the TOTP challenge routes.
 *
 * Allowed states:
 *  - Unauthenticated AND session has `pending_2fa_user_id` → pass through (normal challenge flow)
 *
 * Terminal states that must never reach the challenge form:
 *  - Fully authenticated AND 2fa_confirmed → already done, send to home
 *  - Fully authenticated AND 2fa_confirmed missing → invalid state, force re-login
 *  - No pending_2fa_user_id in session and not authenticated → nothing to challenge, send to login
 */
class EnsurePendingTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        // Already fully logged in
        if (Auth::check()) {
            if ($request->session()->get('2fa_confirmed')) {
                // Completed login — no need to be here
                return redirect()->route('home');
            }

            // Authenticated but 2fa_confirmed missing: invalid state.
            // Force a clean logout so the user can start fresh.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Your session state is invalid. Please sign in again.');
        }

        // Not authenticated — must have a pending 2FA state to be here
        if (! $request->session()->has('pending_2fa_user_id')) {
            return redirect()->route('login')
                ->with('status', 'Please sign in to continue.');
        }

        return $next($request);
    }
}
