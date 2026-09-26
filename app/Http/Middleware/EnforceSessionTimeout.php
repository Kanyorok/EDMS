<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    /**
     * Enforce server-side idle timeout for database sessions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip static asset requests like favicon
        if ($request->is('favicon.ico')) {
            return $next($request);
        }

        $isAuthenticated = Auth::check();
        $isPendingTwoFactor = ! $isAuthenticated && $request->session()->has('pending_2fa_user_id');

        // Nothing to enforce if the user is neither authenticated nor in the 2FA pending state.
        if (! $isAuthenticated && ! $isPendingTwoFactor) {
            return $next($request);
        }

        $allowsPasswordChallenge = $request->routeIs('timeout', 'session.extend', 'session.require-password')
            || $request->is('logout');

        if ($isAuthenticated && $request->session()->get('password_reauth_required') === true && ! $allowsPasswordChallenge) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $loginUrl = route('login');

            if ($request->headers->has('HX-Request')) {
                return redirect()->to($loginUrl)
                    ->with('status', 'Please log in again to continue.')
                    ->withHeaders(['HX-Redirect' => $loginUrl]);
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Please log in again to continue.'], 401)
                    ->withHeaders(['HX-Redirect' => $loginUrl]);
            }

            return redirect()->to($loginUrl)
                ->with('status', 'Please log in again to continue.');
        }

        // Skip for non-database session drivers (cannot query last_activity).
        if (config('session.driver') !== 'database') {
            return $next($request);
        }

        // Avoid checking/updating the timeout on internal endpoints that shouldn't touch user activity metric
        if ($request->routeIs('timeout') || $request->routeIs('session.extend') || $request->routeIs('session.require-password') || $request->routeIs('session.touch')) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();
        $connection = config('session.connection');
        $table = config('session.table', 'sessions');

        // Define idle threshold from new idle config, independent of PHP session expiration
        $profileTimeout = (int)(\App\Models\Settings\OrganizationProfile::current()?->IdleSessionTimeoutMinutes);
        $lifetimeSeconds = ($profileTimeout ?: (int) config('idle.timeout_minutes', 20)) * 60;

        try {
            $row = DB::connection($connection)->table($table)->where('id', $sessionId)->first();
        } catch (\Throwable $e) {
            return $next($request); // Let request pass if we can't verify session
        }

        if ($row && isset($row->last_activity)) {
            $idleSeconds = time() - (int) $row->last_activity;
            $warningSeconds = min((int) config('idle.warning_seconds_before', 120), max($lifetimeSeconds - 1, 1));
            $isInsidePasswordWarningWindow = $idleSeconds >= ($lifetimeSeconds - $warningSeconds);

            if ($idleSeconds > $lifetimeSeconds || $isInsidePasswordWarningWindow) {
                // For a fully-authenticated session, also clear the auth guard state.
                if ($isAuthenticated) {
                    Auth::guard('web')->logout();
                }

                // Clear the pending 2FA state so the challenge page cannot be
                // revisited after the session has timed out — without this the
                // user's browser could bounce between /login/two-factor and
                // /login in a redirect loop when the session expires mid-challenge.
                if ($isAuthenticated && $request->user()) {
                    $request->user()->forceFill(['CurrentSessionId' => null])->save();
                }

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $loginUrl = route('login');
                $message = $idleSeconds > $lifetimeSeconds
                    ? 'Session expired due to inactivity. Please log in again.'
                    : 'Password confirmation is required. Please log in again.';

                if ($request->headers->has('HX-Request')) {
                    return redirect()->to($loginUrl)
                        ->with('status', $message)
                        ->withHeaders(['HX-Redirect' => $loginUrl]);
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['message' => $message], 401)
                        ->withHeaders(['HX-Redirect' => $loginUrl]);
                }

                return redirect()->to($loginUrl)
                    ->with('status', $message);
            }
        }

        return $next($request);
    }
}
