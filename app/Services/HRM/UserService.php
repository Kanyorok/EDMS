<?php

namespace App\Services\HRM;

use App\Enums\EmailPriorityEnum;
use App\Enums\Employee\GenderEnum;
use App\Exceptions\ErroredException;
use App\Helpers\SystemHelper;
use App\Models\Auth\User;
use App\Models\Core\Branch;
use App\Models\HRM\Employee;
use App\Services\CRMEmailService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;
use Yajra\DataTables\DataTables;

class UserService
{
    public const MODULE = 'USERS';

    public function __construct(public User $user)
    {
    }

    public static function create(Employee $employee, User $actor): UserService
    {
        $user = $employee->user;
        if ($user instanceof User) {
            if ($user->trashed()) {
                $user->forceFill([
                    'Name' => $employee->full_name,
                    'Email' => $employee->Email,
                    'Phone' => $employee->Phone,
                    'Password' => Str::random(10),
                    'ModifiedBy' => $actor->Id,
                    'DeletedOn' => null,
                    'DeletedBy' => null,
                ])->save();
            }

            return new self($user);
        }
        $user = User::create([
            'UserID' => self::generateUserID($employee->FirstName, $employee->LastName),
            'Name' => $employee->full_name,
            'Email' => $employee->Email,
            'Phone' => $employee->Phone,
            'ImageId' => $employee->ImageId,
            'EmployeeId' => $employee->Id,
            'Password' => Str::random(10),
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);

        return new self($user);
    }

    protected static function generateUserID(string $FirstName, string $Surname): string
    {
        $baseId = Str::of($FirstName)->trim()->substr(0, 1) .
            Str::of($Surname)->trim()->slug('')->upper()->toString();
        $number = 0;
        do {
            $userId = $number === 0 ? Str::of($baseId) : Str::of($baseId . $number);
            $number++;
        } while (User::query()->where('UserID', $userId->slug('')->upper()->toString())->withTrashed()->exists());

        return $userId->slug('')->upper()->toString();
    }

    /**
     * @throws Exception
     */
    public static function dt(Builder|BelongsToMany $query, array $with = [], array $extra = []): JsonResponse
    {
        if (! empty($with)) {
            $query->with($with);
        }

        return Datatables::of(
            $query->where('t_Users.UserID', '!=', SystemHelper::ID)
                ->lock('WITH(NOLOCK)')
                ->select('*')
        )
            ->addColumn('action', function (User $user) use ($extra) {
                if (array_key_exists('action_team', $extra)) {
                    $destroyRoute = route('team-users.destroy', [$extra['action_team'], $user->UserID]);

                    return '<button type="button"  data-action="' . $destroyRoute .
                        '" data-name="' . $user->Name .
                        '" class="btn btn-danger btn-sm modal-trash-team-users"><i class="fas fa-trash"></i></button>';
                }

                return '
                    <a href="' . route('users.show', [$user->UserID]) . '" class="btn app-button app-button--outline-primary app-button--sm users-details-button">
                        <i class="ti ti-user-circle" aria-hidden="true"></i><span>View</span>
                    </a>';
            })->editColumn('pivot', function (User $user) use ($extra) {
                if (! in_array('pivot_date', $extra, true)) {
                    return '';
                }

                try {
                    return Carbon::parse($user->pivot->CreatedOn)->format('M d, Y h:i A');
                } catch (Exception) {
                }

                return $user->pivot->CreatedOn;
            })->editColumn('Name', function (User $user) {
                return $user->Name;
            })->editColumn('photo', function (User $user) use ($with) {
                return (in_array('photo', $with, true)) ?
                    $user->getImage('class="img-thumbnail" style="height: 70px;width: 70px; max-width: inherit;"')
                    : '';
            })->setRowClass('mouse_pointer user-select-none dbl-click-redirect-data')->setRowData([
                'dbl_click_url' => function (User $user) {
                    return route('users.show', [$user->UserID]);
                },
            ])->rawColumns(['action', 'photo'])->make();
    }

    public function setRole(Role $role, Branch $branch, User $actor): static
    {
        // Assign system-level role using Spatie (optional if you're not using permission checks globally)
        $this->user->syncRolesWithBranch([$role->name], $branch->Id, $actor->Id);

        // Flush Spatie permission cache to ensure immediate effect
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
        return $this;
    }

    public function update(
        string $UserID,
        string $Name,
        string $Email,
        string $Phone,
        GenderEnum $Gender,
        User $actor,
        string $Signature = '',
        string $Notes = '',
        $branch = null
    ): static {
        $originalBranchNumericId = $this->user->BranchId ?? null;
        $email_change = ($this->user->Email === $Email) ? null : $this->user->Email;
        $this->user->update([
            'UserID' => $UserID,
            'Name' => $Name,
            'Email' => $Email,
            'Phone' => $Phone,
            'Gender' => $Gender->value,
            // t_Users.BranchId references t_Branches.Id. BranchID is the
            // human-facing branch code (for example "00"), not the FK value.
            'BranchId' => ($branch instanceof Branch) ? $branch->Id : $this->user->BranchId,
            'Notes' => $Notes,
            'Email_Signature' => $Signature,
            'ModifiedBy' => $actor->Id,
            'ModifiedOn' => now(),
        ]);
        // If branch changed and we maintain one-branch-per-user mapping, move the role mapping to new branch
        if ($branch instanceof Branch) {
            try {
                // Move any existing model role entries for old branch to the new branch id
                $roleAssignments = \App\Models\Auth\ModelRole::where('model_id', $this->user->Id)
                    ->where('model_type', User::getPrimaryKey())
                    ->where('BranchId', $originalBranchNumericId)
                    ->get();

                foreach ($roleAssignments as $roleAssignment) {
                    $roleAssignment->forceFill([
                        'BranchId' => $branch->Id,
                        'ModifiedBy' => $actor->Id,
                        'ModifiedOn' => now(),
                    ])->save();
                }

                // Flush permission cache for immediate effect
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
                // non-blocking
            }
        }

        if (! is_null($email_change)) {
            $oldEmailRecipient = [[$Name => $email_change]];
            $emailBody = '<div><p>Hello ' . $this->user->Name . ' </p>' .
                '<p>Your email has been changed from <b>' . $email_change . '</b> to <b>' .
                $this->user->Email . '</b> </p>' .
                '<p>if this was a mistake, contact support</p></div>';

            $this->sendEmail('Email Changed in Crm', $emailBody, $oldEmailRecipient);
        }

        return $this;
    }

    public function sendEmail(
        string $subject,
        string $body,
        array $cc = [],
        bool|null $immediate = false,
        EmailPriorityEnum $priorityEnum = EmailPriorityEnum::Normal,
        $email = null
    ): ?CRMEmailService {
        if (SystemHelper::isSystem($this->user)) {
            return null;
        }

        $service = CRMEmailService::createUser(
            $this->user,
            $subject,
            $body,
            SystemHelper::user(),
            $cc,
            $priorityEnum,
            $email
        );
        if (is_null($immediate)) {
            return $service;
        }

        return $service->send($immediate);
    }

    public function welcomeEmail(): static
    {
        if (now()->subDays(2)->startOfDay()->greaterThan($this->user->CreatedOn)) {
            return $this;
        }

        $body = '<div><p>Hello ' . $this->user->Name . ',</p>';
        $body .= '<p>An account has been created for you in ' . config('app.name') . '.</p>';

        $body .= '<p>Your UserID is: <b>' . $this->user->UserID . '</b></p>';
        $body .= $this->branchRoleAssignmentsHtml();
        $body .= $this->resetPasswordBlockHtml();

        $body .= '</div>';

        $this->sendEmail(
            'New account created in ' . config('app.name'),
            $body,
            priorityEnum: EmailPriorityEnum::Important
        );

        return $this;
    }

    private function branchRoleAssignmentsHtml(): string
    {
        $assignments = \App\Models\Auth\ModelRole::where('model_id', $this->user->Id)
            ->where('model_type', User::getPrimaryKey())
            ->with(['branch', 'role'])
            ->get();

        if ($assignments->isEmpty()) {
            return '';
        }

        $lines = $assignments
            ->map(fn ($assignment) => 'Branch: ' . ($assignment->branch->Name ?? 'N/A') .
                ' — Role: ' . ($assignment->role->name ?? 'N/A'))
            ->implode('<br>');

        return '<p>You have been assigned the following branch and role:</p><p>' . $lines . '</p>';
    }

    private function resetPasswordBlockHtml(): string
    {
        return '<p>You are receiving this email because we received ' .
            'a password reset request for your account.</p>' .
            '<p><a href="' . $this->createResetURL() . '">Reset Password</a></p>' .
            '<p>This password reset link will expire in 60 minutes.<br>' .
            'If you did not request a password reset, no further action is required.</p>';
    }

    private function createResetURL(): string
    {
        $token = Password::createToken($this->user);
        $email = urlencode($this->user->Email);

        return config('app.url') . '/reset-password/' . $token . '?email=' . $email;
    }

    public function sendPasswordResetNotification(): static
    {
        $this->sendEmail('Reset Password Notification', $this->resetPasswordBlockHtml());

        return $this;
    }

    /**
     * @throws ErroredException
     */
    public function trash(User $actor): void
    {
        try {
            DB::transaction(function () use ($actor) {
                $this->user->forceFill(['DeletedBy' => $actor->Id])->saveQuietly();
                $this->user->delete();

                $deleteEmailBody = '<p>Hello ' . $this->user->Name . '<br>' .
                    'Your account has just been deleted <br>' .
                    ' If you have any questions or concerns, feel free to reach out to our support team </p>';

                $this->sendEmail('account deleted', $deleteEmailBody);
            });
        } catch (Throwable | ErroredException $e) {
            Log::error('Error delete user ' . $e->getMessage());

            throw new ErroredException();
        }
    }
}
