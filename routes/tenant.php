<?php

use App\Http\Controllers\Tenant\AcceptInvitationController;
use App\Http\Controllers\Tenant\InvitationController;
use App\Http\Controllers\Tenant\LoginHandoffController;
use App\Http\Controllers\Tenant\OnboardingController;
use App\Http\Controllers\Tenant\ServiceController;
use App\Http\Controllers\Tenant\TeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes ({slug}.{APP_DOMAIN})
|--------------------------------------------------------------------------
*/

Route::get('auth/handoff/{token}', LoginHandoffController::class)
    ->middleware('throttle:10,1')
    ->name('login.handoff');

Route::middleware('guest')->group(function () {
    Route::get('invitations/{invitation}', [AcceptInvitationController::class, 'show'])
        ->middleware('signed:relative')
        ->name('invitations.show');
    Route::post('invitations/{invitation}', [AcceptInvitationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitations.accept');
});

Route::middleware(['auth', 'tenant.member'])->prefix('app')->group(function () {
    Route::inertia('/', 'Tenant/Dashboard')->name('dashboard');
    Route::get('onboarding', OnboardingController::class)->name('onboarding');

    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team/invitations', [InvitationController::class, 'store'])->name('team.invitations.store');
    Route::delete('team/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('team.invitations.destroy');

    Route::put('services/order', [ServiceController::class, 'reorder'])->name('services.reorder');
    Route::resource('services', ServiceController::class)->only(['index', 'store', 'update', 'destroy']);

    require __DIR__.'/settings.php';
});
