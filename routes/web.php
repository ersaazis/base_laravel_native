<?php

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\DashboardController;
use App\Http\Controllers\Mobile\NotificationController;
use App\Http\Controllers\Mobile\ProfileController;
use App\Http\Controllers\Mobile\SecurityController;
use App\Http\Controllers\Mobile\SettingsController;
use App\Http\Controllers\Mobile\StartupController;
use App\Http\Middleware\EnsureMobileAuthenticated;
use App\Http\Middleware\EnsureMobileUnlocked;
use Illuminate\Support\Facades\Route;

Route::get('/', [StartupController::class, 'show'])->name('startup');
Route::post('/startup/check', [StartupController::class, 'check'])->name('startup.check');

Route::middleware('guest')->group(function (): void {
    Route::get('/language', fn () => redirect()->route('login'));
    Route::match(['post', 'patch'], '/language', [AuthController::class, 'updateLocale'])->name('language.update');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/two-factor-challenge', [AuthController::class, 'twoFactorChallengeForm'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->name('two-factor.challenge.store');
    Route::get('/signup', [AuthController::class, 'signupForm'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->name('signup.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotPasswordForm'])->name('password.forgot');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::get('/reset-password', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware([EnsureMobileAuthenticated::class])->group(function (): void {
    Route::get('/unlock', [SettingsController::class, 'unlockForm'])->name('settings.unlock');
    Route::post('/unlock', [SettingsController::class, 'unlock'])->name('settings.unlock.store');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(EnsureMobileUnlocked::class)->group(function (): void {
        Route::get('/home', DashboardController::class)->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::match(['post', 'patch'], '/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/language', fn () => redirect()->route('profile.edit'));
        Route::match(['post', 'patch'], '/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.language.update');

        Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
        Route::get('/security/password', fn () => redirect()->route('security.index'));
        Route::match(['post', 'put'], '/security/password', [SecurityController::class, 'updatePassword'])->name('security.password.update');
        Route::get('/security/two-factor', fn () => redirect()->route('security.index'));
        Route::post('/security/two-factor', [SecurityController::class, 'enableTwoFactor'])->name('security.two-factor.enable');
        Route::post('/security/two-factor/confirm', [SecurityController::class, 'confirmTwoFactor'])->name('security.two-factor.confirm');
        Route::post('/security/two-factor/cancel', [SecurityController::class, 'cancelTwoFactorSetup'])->name('security.two-factor.cancel');
        Route::post('/security/two-factor/disable', [SecurityController::class, 'disableTwoFactor'])->name('security.two-factor.disable');
        Route::delete('/security/two-factor', [SecurityController::class, 'disableTwoFactor']);
        Route::post('/security/two-factor/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->name('security.two-factor.recovery-codes');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/status', [NotificationController::class, 'status'])->name('notifications.status');
        Route::match(['post', 'patch'], '/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::match(['post', 'patch'], '/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/biometrics', [SettingsController::class, 'enableBiometrics'])->name('settings.biometrics.enable');
        Route::match(['post', 'delete'], '/settings/biometrics/disable', [SettingsController::class, 'disableBiometrics'])->name('settings.biometrics.disable');
        Route::post('/settings/lock', [SettingsController::class, 'lock'])->name('settings.lock');
    });
});
