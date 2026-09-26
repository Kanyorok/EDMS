<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::post('session/relogin', [AuthenticatedSessionController::class, 'relogin'])->name('session.relogin');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');

});

// TOTP challenge routes — guarded by EnsurePendingTwoFactor to safely handle
// all session states without conflicting with the guest or auth middleware groups.
Route::middleware('pending_2fa')->group(function () {
    Route::get('login/two-factor',    [AuthenticatedSessionController::class, 'twoFactorChallenge'])->name('two-factor.challenge');
    Route::post('login/two-factor',   [AuthenticatedSessionController::class, 'verifyTwoFactor'])->name('two-factor.verify')->middleware('throttle:5,1');
    Route::delete('login/two-factor', [AuthenticatedSessionController::class, 'cancelTwoFactor'])->name('two-factor.cancel');
});

Route::middleware(['auth', 'ensure_2fa'])->group(function () {
    Route::get('/', DashboardController::class)->name('home');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::post('timeout', [AuthenticatedSessionController::class, 'timeout'])->name('timeout');
    Route::post('session/touch', [AuthenticatedSessionController::class, 'touch'])->name('session.touch');
    Route::post('session/require-password', [AuthenticatedSessionController::class, 'requirePassword'])->name('session.require-password');
    Route::post('session/extend', [AuthenticatedSessionController::class, 'extend'])->name('session.extend');

    Route::get('user', [ProfileController::class, 'profile'])->name('profile');
    Route::put('user', [ProfileController::class, 'updateUser']);
    Route::post('user', [ProfileController::class, 'updateCred'])->name('profile.cred');
    Route::post('user/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');

    // 2FA management (on the profile page)
    Route::get('user/two-factor',    [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('user/two-factor',   [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::delete('user/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

});
