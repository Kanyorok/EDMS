<?php

namespace App\Http\Requests\Auth;

use App\Enums\Core\PermissionEnum;
use App\Exceptions\TwoFactorRequiredException;
use App\Models\Auth\ModelRole;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Core\Branch;
use App\Models\HRM\Employee;
use App\Services\Auth\UserPasswordService;
use App\Services\Core\ModuleService;
use App\Support\Audit\EndpointContext;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'UserID' => ['required', 'string'],
            'password' => ['required', 'string'],
            'branch' => ['required', 'string', 'max:200'],
        ];
    }

    public function getBranch(User $user): array
    {
        $branch = Branch::query()->where('BranchID', $this->string('branch'))->first();
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch' => 'Branch not found or not authorized.',
            ]);
        }

        $modelRole = ModelRole::query()->where('model_id', $user->Id)
            ->where('model_type', User::getPrimaryKey())
            ->where('BranchId', $branch->Id)
            ->with('role')
            ->first();

        $role = $modelRole?->role;
        if (! $modelRole instanceof ModelRole || ! $role instanceof Role) {
            throw ValidationException::withMessages([
                'branch' => 'Branch not found or not authorized.',
            ]);
        }

        return ['branch' => $branch, 'role' => $role];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $user = User::query()->where('UserID', $this->string('UserID')->upper()->toString())->first();

        // Load employee bypassing the 'exclude_silent' global scope so that the
        // default system user (CSADM, IsSilent=1) can still log in. IsSilent is
        // an HR-only flag and must not block authentication.
        $employee = $user?->employee()->withoutGlobalScope('exclude_silent')->first();

        if ($user instanceof User && ($employee instanceof Employee) && UserPasswordService::verify($user, $this->validated('password'))) {
            $branchRole = $this->getBranch($user);

            //check if user has a employee profile if not fail.
            RateLimiter::clear($this->throttleKey());


            // TWO-FACTOR AUTHENTICATION GATE
            // If the user has 2FA enabled, do NOT complete login yet.
            // Store pending context in session and signal the controller.

            if ($user->hasTwoFactorEnabled()) {
                $this->session()->put([
                    'pending_2fa_user_id' => $user->Id,
                    'pending_2fa_branch_id' => $branchRole['branch']->Id,
                    'pending_2fa_role_id' => $branchRole['role']->id,
                ]);
                $this->session()->save();

                throw new TwoFactorRequiredException();
            }

            //remove other sessions
            if (config(key: 'session.driver') === 'database') {
                DB::connection(config(key: 'session.connection'))->table(table: config(key: 'session.table', default: 't_SYSSessions'))
                    ->where(column: 'user_id', operator: '=', value: $user->getAuthIdentifier())->delete();
            }

            $user->fill([
                'last_login_at' => now(),
                'BranchId' => $branchRole['branch']->Id,
            ])->save();



            //new session
            Auth::guard('web')->login($user, $branchRole['role']->hasPermissionTo(PermissionEnum::UsersSessions));





            // CRITICAL: Generate session_token for EnsureSingleActiveSession middleware
            // This middleware was added on Sept 23, 2025 but login was never updated
            $sessionToken = Str::random(60);
            session(['session_token' => $sessionToken]);
            \Illuminate\Support\Facades\Cache::put(
                'user_session_token_' . $user->Id,
                $sessionToken,
                now()->addMinutes((int)config('session.lifetime', 120) + 5)
            );

            session([
                'LoginBranchId' => $branchRole['branch']->Id,
                'LoginBranchName' => $branchRole['branch']->Name,
                'LoginRoleName' => $branchRole['role']->name,
                'login_at' => $user->last_login_at,
            ]);

            $endpointContext = EndpointContext::resolve($this);

            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties([
                    'audit' => array_filter([
                        'endpoint_ip' => $endpointContext['ip'],
                        'endpoint_source' => $endpointContext['source'],
                        'mac_address' => $endpointContext['mac'],
                        'host_name' => $endpointContext['hostname'],
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ])
                ->event('authentication')
                ->log('Signed in from ' . ($endpointContext['ip'] ?? 'unknown IP') . ' as ' . $branchRole['role']->name . ' at ' . $branchRole['branch']->Name);

            ModuleService::clearNavbarCache($user);

            // Force save session to DB immediately
            $this->session()->save();

            // Debug: Check DB immediately after save
            $dbSession = DB::table(config('session.table', 't_SYSSessions'))
                ->where('id', session()->getId())
                ->first();



            return;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'UserID' => trans('auth.failed'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'UserID' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * @return string
     */
    public function throttleKey(): string
    {
        return Str::lower($this->input('UserID')) . '|' . $this->ip();
    }
}
