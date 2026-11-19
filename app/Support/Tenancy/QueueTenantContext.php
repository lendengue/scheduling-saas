<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\QueueManager;

/**
 * Carries the tenant into queued work.
 *
 * Every payload pushed to a queue records the current tenant id. When a
 * worker picks the job up, the tenant is restored before the job runs and
 * cleared afterwards. Being payload-based, this covers jobs, queued mail,
 * notifications and listeners alike, without each class opting in.
 *
 * The "sync" connection runs jobs inline, already inside the right context,
 * so it is left alone.
 */
final class QueueTenantContext
{
    public const string PAYLOAD_KEY = 'tenant_id';

    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public static function register(QueueManager $queue, Dispatcher $events): void
    {
        $queue->createPayloadUsing(fn () => [
            self::PAYLOAD_KEY => app(CurrentTenant::class)->id(),
        ]);

        $events->listen(JobProcessing::class, function (JobProcessing $event): void {
            if ($event->connectionName !== 'sync') {
                app(self::class)->restore($event->job->payload()[self::PAYLOAD_KEY] ?? null);
            }
        });

        $events->listen([JobProcessed::class, JobExceptionOccurred::class], function (JobProcessed|JobExceptionOccurred $event): void {
            if ($event->connectionName !== 'sync') {
                app(CurrentTenant::class)->forget();
            }
        });
    }

    public function restore(mixed $tenantId): void
    {
        $tenant = is_string($tenantId) ? Tenant::query()->find($tenantId) : null;

        // A job queued for a tenant that no longer exists runs with no tenant,
        // so the fail-closed scope hides every tenant-owned row.
        $this->currentTenant->set($tenant);
    }
}
