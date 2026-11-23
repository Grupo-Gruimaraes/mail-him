<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
                ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->name('password.store');
});

Route::middleware(['auth', 'two-factor'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
                ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

    Route::get('profile/two-factor', [TwoFactorController::class, 'change'])
                ->middleware('password.confirm')
                ->name('two-factor.change');
});

// Segundo fator: atende o login em andamento (senha já conferida) e a troca de método pelo perfil
Route::prefix('two-factor')->controller(TwoFactorController::class)->group(function () {
    Route::get('challenge', 'challenge')->name('two-factor.challenge');
    Route::post('challenge', 'verifyChallenge');
    Route::post('resend', 'resend')->middleware('throttle:6,1')->name('two-factor.resend');
    Route::get('recovery', 'recovery')->name('two-factor.recovery');
    Route::post('recovery', 'verifyRecovery');
    Route::get('setup', 'setup')->name('two-factor.setup');
    Route::post('setup', 'startSetup')->middleware('throttle:10,1');
    Route::get('setup/confirm', 'confirmSetup')->name('two-factor.setup.confirm');
    Route::post('setup/confirm', 'verifySetup');
    Route::get('recovery-words', 'recoveryWords')->name('two-factor.recovery-words');
    Route::post('recovery-words', 'finish');
    Route::post('cancel', 'cancel')->name('two-factor.cancel');
});
