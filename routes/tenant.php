<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes ({slug}.{APP_DOMAIN})
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'tenant.member'])->prefix('app')->group(function () {
    Route::inertia('/', 'Tenant/Dashboard')->name('dashboard');

    require __DIR__.'/settings.php';
});
