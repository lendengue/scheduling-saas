<?php

use App\Http\Controllers\Central\FindWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Domain Routes ({APP_DOMAIN})
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::post('find-workspace', FindWorkspaceController::class)
        ->middleware('throttle:10,1')
        ->name('workspace.find');
});
