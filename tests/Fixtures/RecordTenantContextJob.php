<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Records what a queued job can see, to prove the tenant context travels
 * with the job.
 */
class RecordTenantContextJob implements ShouldQueue
{
    use Queueable;

    public function handle(CurrentTenant $currentTenant): void
    {
        Cache::put('job.tenant_id', $currentTenant->id());
        Cache::put('job.visible_emails', User::query()->orderBy('email')->pluck('email')->all());
    }
}
