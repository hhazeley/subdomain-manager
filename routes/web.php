<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\BlockedSubdomainController;
use App\Http\Controllers\Admin\ManagedDomainController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DnsRecordController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SubdomainController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('account.password.update');
    Route::post('/account/password-reset', [AccountController::class, 'sendPasswordReset'])
        ->middleware('throttle:6,1')
        ->name('account.password-reset');
    Route::put('/account/email', [AccountController::class, 'updateEmail'])
        ->middleware('throttle:6,1')
        ->name('account.email.update');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/approval-pending', ApprovalController::class)->name('approval.notice');

    Route::middleware(['verified', 'approved'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::post('/subdomains', [SubdomainController::class, 'store'])->name('subdomains.store');
        Route::get('/subdomains/{subdomain}', [SubdomainController::class, 'show'])->name('subdomains.show');
        Route::post('/subdomains/{subdomain}/renew', [SubdomainController::class, 'renew'])->name('subdomains.renew');
        Route::delete('/subdomains/{subdomain}', [SubdomainController::class, 'destroy'])->name('subdomains.destroy');
        Route::post('/subdomains/{subdomain}/records', [DnsRecordController::class, 'store'])->name('records.store');
        Route::put('/subdomains/{subdomain}/records/{record}', [DnsRecordController::class, 'update'])->name('records.update');
        Route::delete('/subdomains/{subdomain}/records/{record}', [DnsRecordController::class, 'destroy'])->name('records.destroy');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::middleware('approver')->group(function () {
                Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
                Route::post('/registrations/{subdomain}/approve', [RegistrationController::class, 'approve'])->name('registrations.approve');
                Route::post('/registrations/{subdomain}/reject', [RegistrationController::class, 'reject'])->name('registrations.reject');
            });

            Route::middleware('admin')->group(function () {
                Route::get('/domains', [ManagedDomainController::class, 'index'])->name('domains.index');
                Route::post('/domains', [ManagedDomainController::class, 'store'])->name('domains.store');
                Route::put('/domains/{domain}', [ManagedDomainController::class, 'update'])->name('domains.update');
                Route::get('/blocked-subdomains', [BlockedSubdomainController::class, 'index'])->name('blocked-subdomains.index');
                Route::post('/blocked-subdomains', [BlockedSubdomainController::class, 'store'])->name('blocked-subdomains.store');
                Route::delete('/blocked-subdomains/{blockedSubdomain}', [BlockedSubdomainController::class, 'destroy'])->name('blocked-subdomains.destroy');
                Route::get('/users', [UserRoleController::class, 'index'])->name('users.index');
                Route::post('/users', [UserRoleController::class, 'store'])->name('users.store');
                Route::put('/users/{user}/role', [UserRoleController::class, 'update'])->name('users.role');
                Route::post('/users/{user}/approve', [UserRoleController::class, 'approve'])->name('users.approve');
                Route::post('/users/{user}/password-reset', [UserRoleController::class, 'sendPasswordReset'])->name('users.password-reset');
                Route::post('/users/{user}/verification', [UserRoleController::class, 'resendVerification'])->name('users.verification');
                Route::delete('/users/{user}', [UserRoleController::class, 'destroy'])->name('users.destroy');
                Route::put('/settings/registration', [UserRoleController::class, 'updateRegistration'])->name('settings.registration');
                Route::get('/updates', [UpdateController::class, 'index'])->name('updates.index');
                Route::post('/updates/install', [UpdateController::class, 'install'])
                    ->middleware('throttle:2,10')
                    ->name('updates.install');
                Route::post('/registrations/{subdomain}/restore', [RegistrationController::class, 'restore'])->name('registrations.restore');
                Route::delete('/registrations/{subdomain}', [RegistrationController::class, 'destroy'])->name('registrations.destroy');
            });
        });
    });
});
