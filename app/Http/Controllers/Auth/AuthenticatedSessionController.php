<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\TwoFactorRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Core\Branch;
use App\Models\Settings\OrganizationProfile;
use App\Services\Auth\UserPasswordService;
use App\Services\Core\ModuleService;
use App\Support\Audit\EndpointContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax')->only('store');
    }

    public function create(): View
    {
        $configuration = OrganizationProfile::current()?->Configuration;

        return view('auth.login')
            ->with('branches', Branch::query()->orderBy('Name')->get(['BranchID', 'Name']))
            ->with('authOrganizationName', $configuration->name ?? config('app.name'))
            ->with('authOrganizationLogo', $configuration->logo ?? 'assets/img/carft.png');
    }

    public function store(LoginRequest $request): JsonResponse
    {
        try {
            // Authenticate — may throw TwoFactorRequiredException if 2FA is enabled
            $request->authenticate();
        } catch (TwoFactorRequiredException) {
            // Credentials are valid but 2FA is required — redirect to challenge page
            return $this->succeeded(
                message: 'Two-factor authentication required.',
                route: route('two-factor.challenge')
            );
        }

        $request->session()->regenerate();
        $request->session()->forget('password_reauth_required');

        $user = Auth::user();
        if ($user) {
            $user->CurrentSessionId = $request->session()->getId();
            $user->save();
        }

        return $this->succeeded(message: 'Logged in successfully.', route: route('home'));
    }

    /**
     * Show the TOTP challenge form for a pending login.
     */
    public function twoFactorChallenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pending_2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor.challenge');
    }

    /**
     * Verify the submitted TOTP code and complete the login.
     *
     * Hardening:
     *  - Tracks failed attempts in session; locks out after 5 and forces re-login.
     *  - Wraps the entire login-completion block in a try-catch so that any
     *    partial write (Auth::login succeeds but session persistence fails) is
     *    caught: the user is logged out and returned to the login page rather than
     *    being left in an authenticated-but-unconfirmed state (which would produce
     *    an infinite redirect loop via EnsureTwoFactorComplete).
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('pending_2fa_user_id');
        if (! $userId) {
            throw ValidationException::withMessages([
                'code' => 'Session expired. Please log in again.',
            ]);
        }

        $user = User::find($userId);
        if (! $user instanceof User) {
            $this->clearPendingTwoFactor($request);

            throw ValidationException::withMessages([
                'code' => 'User not found. Please log in again.',
            ]);
        }

        $endpointContext = EndpointContext::resolve($request);

        // Track failed OTP attempts to prevent brute-force
        $attempts = (int) $request->session()->get('pending_2fa_attempts', 0);
        if ($attempts >= 5) {
            Log::warning('2FA lockout: too many failed attempts for user ' . $user->Id . ' from ' . ($endpointContext['ip'] ?? 'unknown IP'));
            $this->clearPendingTwoFactor($request);

            return response()->json([
                'message' => 'Too many failed attempts. Please sign in again.',
                'redirect' => route('login'),
            ], 422);
        }

        // Decrypt stored secret
        try {
            $secret = Crypt::decryptString($user->two_factor_secret);
        } catch (\Throwable $e) {
            Log::error('2FA decrypt error for user ' . $user->Id . ': ' . $e->getMessage());
            $this->clearPendingTwoFactor($request);

            throw ValidationException::withMessages([
                'code' => 'Internal error. Please contact support.',
            ]);
        }

        $google2fa = app('pragmarx.google2fa');
        $valid = $google2fa->verifyKey($secret, (string) str_replace(' ', '', $request->input('code')));

        if (! $valid) {
            $request->session()->put('pending_2fa_attempts', $attempts + 1);

            $remaining = 4 - $attempts; // how many are left after this failure
            $message = $remaining > 0
                ? 'The code is invalid or expired. Please try again. (' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining)'
                : 'The code is invalid or expired. One more failure will lock this session.';

            throw ValidationException::withMessages(['code' => $message]);
        }

        // Code is correct — retrieve pending branch/role and complete login.
        // Wrapped in try-catch to guard against partial writes (e.g. session
        // persistence failure after Auth::login). If anything throws here, we
        // log out immediately to avoid leaving the user in an invalid state.
        $branchId = $request->session()->get('pending_2fa_branch_id');
        $roleId = $request->session()->get('pending_2fa_role_id');

        try {
            $branch = Branch::find($branchId);
            $role = Role::find($roleId);

            // Remove other active sessions for this user
            if (config(key: 'session.driver') === 'database') {
                \Illuminate\Support\Facades\DB::connection(config(key: 'session.connection'))
                    ->table(table: config(key: 'session.table', default: 't_SYSSessions'))
                    ->where(column: 'user_id', operator: '=', value: $user->getAuthIdentifier())
                    ->delete();
            }

            $user->fill([
                'last_login_at' => now(),
                'BranchId' => $branchId,
            ])->save();

            Auth::guard('web')->login($user, $role?->hasPermissionTo(\App\Enums\Core\PermissionEnum::UsersSessions) ?? false);

            // Session token for EnsureSingleActiveSession
            $sessionToken = Str::random(60);

            $request->session()->regenerate();
            $request->session()->forget(['pending_2fa_user_id', 'pending_2fa_branch_id', 'pending_2fa_role_id', 'pending_2fa_attempts']);
            $request->session()->forget('password_reauth_required');

            Cache::put(
                'user_session_token_' . $user->Id,
                $sessionToken,
                now()->addMinutes((int) config('session.lifetime', 120) + 5)
            );

            // Write all session data in a single call so either all keys land or none do
            $request->session()->put([
                'session_token' => $sessionToken,
                'LoginBranchId' => $branchId,
                'LoginBranchName' => $branch?->Name,
                'LoginRoleName' => $role?->name,
                'login_at' => $user->last_login_at,
                '2fa_confirmed' => true,
            ]);

            $user->CurrentSessionId = $request->session()->getId();
            $user->save();

            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties([
                    'audit' => array_filter([
                        'endpoint_ip' => $endpointContext['ip'] ?? null,
                        'endpoint_source' => $endpointContext['source'] ?? null,
                        'mac_address' => $endpointContext['mac'] ?? null,
                        'host_name' => $endpointContext['hostname'] ?? null,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ])
                ->event('authentication')
                ->log('Signed in (2FA verified) from ' . (($endpointContext['ip'] ?? null) ?: 'unknown IP') . ' at ' . ($branch?->Name ?? 'unknown'));

            ModuleService::clearNavbarCache($user);
            $request->session()->save();
        } catch (\Throwable $e) {
            // Something went wrong after TOTP was verified. Force a clean logout
            // so the user is never left in an authenticated-but-unconfirmed state.
            Log::error('2FA login completion failed for user ' . $user->Id . ': ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'code' => 'An error occurred while completing sign-in. Please try again.',
            ]);
        }

        return $this->succeeded(message: 'Logged in successfully.', route: route('home'));
    }

    /**
     * Cancel a pending 2FA challenge and return the user to login.
     */
    public function cancelTwoFactor(Request $request): RedirectResponse
    {
        $this->clearPendingTwoFactor($request);

        return redirect()->route('login');
    }

    /**
     * Clear all pending 2FA session state and regenerate the CSRF token.
     */
    private function clearPendingTwoFactor(Request $request): void
    {
        $request->session()->forget([
            'pending_2fa_user_id',
            'pending_2fa_branch_id',
            'pending_2fa_role_id',
            'pending_2fa_attempts',
        ]);
        $request->session()->regenerateToken();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $endpointContext = EndpointContext::resolve($request);
        activity()
            ->causedBy($actor)
            ->performedOn($actor)
            ->withProperties([
                'audit' => array_filter([
                    'endpoint_ip' => $endpointContext['ip'],
                    'endpoint_source' => $endpointContext['source'],
                    'mac_address' => $endpointContext['mac'],
                    'host_name' => $endpointContext['hostname'],
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ])
            ->event('authentication')
            ->log('Signed Out from ' . ($endpointContext['ip'] ?? 'unknown IP'));

        ModuleService::clearNavbarCache($actor);
        $actor->update([
            'BranchId' => null,
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Logout on timeout
     */
    public function timeout(Request $request): JsonResponse
    {
        $endpointContext = EndpointContext::resolve($request);
        activity()
            ->causedBy($request->user())
            ->performedOn($request->user())
            ->withProperties([
                'audit' => array_filter([
                    'endpoint_ip' => $endpointContext['ip'],
                    'endpoint_source' => $endpointContext['source'],
                    'mac_address' => $endpointContext['mac'],
                    'host_name' => $endpointContext['hostname'],
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ])
            ->event('authentication')
            ->log('Session timeout after 1 minute on ' . ($endpointContext['ip'] ?? 'unknown IP'));

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->put('status', 'Session timeout, please login again.');

        return $this->succeeded('Session timeout, please login again.');
    }

    public function touch(Request $request): JsonResponse
    {
        if (! $request->user() instanceof User) {
            return $this->errored('Your session has expired. Please log in again.', status: 401);
        }

        if ($request->session()->get('password_reauth_required') === true) {
            return $this->errored('Password confirmation is required to continue.', status: 401);
        }

        $request->session()->put('last_session_touch_at', now()->toIso8601String());
        $request->session()->save();

        return $this->succeeded('Session synced.', data: [
            'status' => 'session_synced',
        ]);
    }

    public function requirePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->errored('Your session has expired. Please log in again.', status: 401);
        }

        $role = $user->role();
        $challengeToken = Str::random(64);
        $challengeTtl = max((int) config('idle.warning_seconds_before', 120) + 60, 180);

        Cache::put('session_reauth_challenge_' . $challengeToken, [
            'user_id' => $user->Id,
            'branch_id' => session('LoginBranchId') ?: $user->BranchId,
            'role_id' => $role?->id,
            'intended_url' => $request->input('intended_url'),
        ], now()->addSeconds($challengeTtl));

        $endpointContext = EndpointContext::resolve($request);

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties([
                'audit' => array_filter([
                    'endpoint_ip' => $endpointContext['ip'] ?? null,
                    'endpoint_source' => $endpointContext['source'] ?? null,
                    'mac_address' => $endpointContext['mac'] ?? null,
                    'host_name' => $endpointContext['hostname'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ])
            ->event('authentication')
            ->log('Session ended for password re-authentication from ' . (($endpointContext['ip'] ?? null) ?: 'unknown IP'));

        $user->CurrentSessionId = null;
        $user->save();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->succeeded('Password confirmation required.', data: [
            'status' => 'reauth_required',
            'reauth_token' => $challengeToken,
            'csrf_token' => csrf_token(),
            'login_url' => route('login'),
        ]);
    }

    public function relogin(Request $request): JsonResponse
    {
        $request->validate([
            'reauth_token' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $cacheKey = 'session_reauth_challenge_' . $request->input('reauth_token');
        $challenge = Cache::get($cacheKey);

        if (! is_array($challenge)) {
            return $this->errored('Your session has expired. Please log in again.', [
                'login_url' => route('login'),
            ], 401);
        }

        $user = User::query()->find($challenge['user_id'] ?? null);
        if (! $user instanceof User || ! UserPasswordService::verify($user, $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => 'The password you entered is incorrect.',
            ]);
        }

        $branch = Branch::query()->find($challenge['branch_id'] ?? null);
        $role = Role::query()->find($challenge['role_id'] ?? null);

        if (! $branch instanceof Branch || ! $role instanceof Role) {
            Cache::forget($cacheKey);

            return $this->errored('Your login context has expired. Please log in again.', [
                'login_url' => route('login'),
            ], 401);
        }

        $hasBranchRole = \App\Models\Auth\ModelRole::query()
            ->where('model_id', $user->Id)
            ->where('model_type', User::getPrimaryKey())
            ->where('BranchId', $branch->Id)
            ->where('role_id', $role->id)
            ->exists();

        if (! $hasBranchRole) {
            Cache::forget($cacheKey);

            return $this->errored('Your login permissions changed. Please log in again.', [
                'login_url' => route('login'),
            ], 401);
        }

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 't_SYSSessions'))
                ->where('user_id', '=', $user->getAuthIdentifier())
                ->delete();
        }

        $user->fill([
            'last_login_at' => now(),
            'BranchId' => $branch->Id,
        ])->save();

        Auth::guard('web')->login($user, $role->hasPermissionTo(\App\Enums\Core\PermissionEnum::UsersSessions));

        $sessionToken = Str::random(60);

        $request->session()->regenerate();
        $request->session()->put([
            'session_token' => $sessionToken,
            'LoginBranchId' => $branch->Id,
            'LoginBranchName' => $branch->Name,
            'LoginRoleName' => $role->name,
            'login_at' => $user->last_login_at,
            '2fa_confirmed' => true,
        ]);
        $request->session()->forget(['password_reauth_required', 'password_reauth_required_at']);

        Cache::put(
            'user_session_token_' . $user->Id,
            $sessionToken,
            now()->addMinutes((int) config('session.lifetime', 120) + 5)
        );

        $user->CurrentSessionId = $request->session()->getId();
        $user->save();

        Cache::forget($cacheKey);
        ModuleService::clearNavbarCache($user);
        $request->session()->save();

        $endpointContext = EndpointContext::resolve($request);

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties([
                'audit' => array_filter([
                    'endpoint_ip' => $endpointContext['ip'] ?? null,
                    'endpoint_source' => $endpointContext['source'] ?? null,
                    'mac_address' => $endpointContext['mac'] ?? null,
                    'host_name' => $endpointContext['hostname'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ])
            ->event('authentication')
            ->log('Signed back in from session timeout password modal at ' . $branch->Name);

        return $this->succeeded('Logged in successfully.', route: $challenge['intended_url'] ?: route('home'), data: [
            'status' => 'session_recreated',
        ]);
    }

    public function extend(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->errored('Your session has expired. Please log in again.', status: 401);
        }

        if (! UserPasswordService::verify($user, $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => 'The password you entered is incorrect.',
            ]);
        }

        $request->session()->put('last_password_confirmed_at', now()->toIso8601String());
        $request->session()->forget(['password_reauth_required', 'password_reauth_required_at']);
        $request->session()->save();

        $endpointContext = EndpointContext::resolve($request);

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties([
                'audit' => array_filter([
                    'endpoint_ip' => $endpointContext['ip'] ?? null,
                    'endpoint_source' => $endpointContext['source'] ?? null,
                    'mac_address' => $endpointContext['mac'] ?? null,
                    'host_name' => $endpointContext['hostname'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ])
            ->event('authentication')
            ->log('Session extended after password confirmation from ' . (($endpointContext['ip'] ?? null) ?: 'unknown IP'));

        return $this->succeeded('Session extended.', data: [
            'status' => 'session_renewed',
        ]);
    }
}
