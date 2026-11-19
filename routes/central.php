<?php

use App\Http\Controllers\Central\FindWorkspaceController;
use App\Http\Controllers\Central\RegisterTenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Domain Routes ({APP_DOMAIN})
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterTenantController::class, 'create'])->name('register');
    Route::post('register', [RegisterTenantController::class, 'store'])
        ->middleware('throttle:tenant-registration')
        ->name('register.store');

    Route::post('find-workspace', FindWorkspaceController::class)
        ->middleware('throttle:10,1')
        ->name('workspace.find');
});
