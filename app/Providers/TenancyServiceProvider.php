<?php

namespace App\Providers;

use App\Support\CurrentTenant;
use App\Support\Tenancy\QueueTenantContext;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        QueueTenantContext::register($this->app->make(QueueManager::class), $this->app->make(Dispatcher::class));
    }
}
