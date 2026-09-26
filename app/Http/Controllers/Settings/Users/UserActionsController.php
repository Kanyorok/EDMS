<?php

namespace App\Http\Controllers\Settings\Users;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserActionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax');
    }

    /**
     * Send Password reset email.
     * @throws AuthorizationException
     */
    public function sendResetLink(User $user): JsonResponse
    {
        try {
            $this->authorize('update', $user);
        } catch (AuthorizationException) {
            return $this->errored('You do not have permission to send password reset links.', status: 403);
        }

        if (! filter_var($user->Email, FILTER_VALIDATE_EMAIL)) {
            return $this->errored('This user does not have a valid email address for password reset.');
        }

        try {
            $user->sendPasswordResetNotification();
        } catch (Throwable $e) {
            Log::error('Failed to send user password reset link', [
                'user_id' => $user->Id,
                'user_uid' => $user->UserID,
                'email' => $user->Email,
                'error' => $e->getMessage(),
            ]);

            return $this->errored('Password reset link could not be sent. Please check the mail configuration and try again.');
        }

        return $this->succeeded('password reset sent.');
    }

}
