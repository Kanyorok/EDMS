<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Services\Auth\UserPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax')->except('setup');
    }

    /**
     * Show the 2FA setup page — generates a fresh secret and QR code.
     * If the user already has confirmed 2FA, the view shows the "enabled" state.
     */
    public function setup(Request $request): View
    {
        $user = $request->user();

        // If already enabled, just show the profile section state
        if ($user->hasTwoFactorEnabled()) {
            return view('auth.two-factor._setup-form', ['enabled' => true, 'qrCode' => null, 'secret' => null]);
        }

        $google2fa = app('pragmarx.google2fa');

        // Generate a new secret and store it temporarily (unconfirmed)
        $secret = $google2fa->generateSecretKey();

        // Store raw (will be encrypted on confirm)
        session(['2fa_pending_secret' => $secret]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->Email ?? $user->UserID,
            $secret
        );

        return view('auth.two-factor._setup-form', [
            'enabled' => false,
            'qrCodeUrl' => $qrCodeUrl,
            'secret' => $secret,
            'user' => $user,
        ]);

    }

    /**
     * Confirm the 2FA setup with a valid TOTP code, then persist the encrypted secret.
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $secret = session('2fa_pending_secret');
        if (! $secret) {
            return $this->errored('Session expired. Please start the setup again.');
        }

        $google2fa = app('pragmarx.google2fa');
        $valid = $google2fa->verifyKey($secret, $request->input('code'));

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'The code is invalid. Please try again.',
            ]);
        }

        $user = $request->user();
        $user->fill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // Clear temporary session key and mark current session as confirmed
        $request->session()->forget('2fa_pending_secret');
        $request->session()->put('2fa_confirmed', true);

        activity()->causedBy($user)->performedOn($user)->event('2fa')->log('Enabled two-factor authentication.');

        return $this->succeeded('Two-factor authentication has been enabled.');
    }

    /**
     * Disable 2FA for the authenticated user after verifying their current password.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! UserPasswordService::verify($user, $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => 'The password you entered is incorrect.',
            ]);
        }

        $user->fill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Remove 2FA confirmation flag from this session
        $request->session()->forget('2fa_confirmed');
        $request->session()->forget('2fa_pending_secret');

        activity()->causedBy($user)->performedOn($user)->event('2fa')->log('Disabled two-factor authentication.');

        return $this->succeeded('Two-factor authentication has been disabled.', route('profile'));
    }
}
