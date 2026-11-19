<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;
use RuntimeException;

/**
 * Holds the tenant of the current request, job or command.
 *
 * Registered as a scoped singleton: it is reset between requests (Octane)
 * and between queued jobs.
 */
final class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function getOrFail(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException('No tenant is set for the current context.');
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Run the callback in the context of the given tenant (or of no tenant),
     * restoring the previous context afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(?Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
