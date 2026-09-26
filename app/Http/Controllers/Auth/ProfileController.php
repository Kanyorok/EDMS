<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AvatarRequest;
use App\Http\Requests\Auth\UserRequest;
use App\Services\Auth\UserPasswordService;
use App\Services\HRM\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax')->except('profile');
    }

    public function profile(Request $request): View
    {
        return view('auth.profile')->with('user', $request->user());
    }

    /**
     * @throws ValidationException
     */
    public function updateCred(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'current_password' => 'required',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ], ['current_password.required' => 'Please enter current password']);

        if (! UserPasswordService::verify($user, $request->current_password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect']);
        }

        $user->fill([
            'Password' => UserPasswordService::hash($user, $request->password),
        ])->save();

        activity()->causedBy($user)->performedOn($user)->event('password-change')->log('changed password.');

        return $this->succeeded('password updated successfully');
    }

    /**
     * Update User Profile
     *
     * @throws ValidationException
     */
    public function updateUser(UserRequest $request): JsonResponse
    {
        $user = $request->user();
        $gender = $request->getGender();
        $email = $request->getUserEmail($user);
        $userID = $request->getUserID($user);
        try {
            DB::transaction(static function () use ($user, $userID, $email, $gender, $request) {
                (new UserService($user))
                    ->update(
                        $userID,
                        $request->validated('Name'),
                        $email,
                        $request->validated('Phone'),
                        $gender,
                        $request->user(),
                        $request->validated('Signature'),
                        ($user->Notes) ?? '',
                        null
                    );
            });
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (Exception $e) {
            Log::error('Error create user ' . $e->getMessage());

            return $this->errored('unexpected error, try again later');
        }

        return $this->succeeded('account updated successfully.', route('profile'));
    }

    public function avatar(AvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $user->setImage($request->file('image'), $user, 'ImageId');
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (\Throwable | Exception $e) {
            Log::error('Error upload avatar ' . $e->getMessage());

            return $this->errored('unexpected error, try again later');
        }

        return $this->succeeded('Avatar Uploaded Successfully', data: ['avatar' => $user->photo?->image_src]);
    }
}
