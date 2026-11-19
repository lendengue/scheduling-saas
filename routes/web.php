<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes are not bound to a domain. IdentifyTenant resolves the tenant from
| the host, and the "central" and "tenant" middleware restrict each group to
| its side. Fortify's authentication routes are shared: the tenant scope on
| User makes them work against the current tenant.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::middleware('central')->group(__DIR__.'/central.php');

Route::middleware('tenant')->group(__DIR__.'/tenant.php');
