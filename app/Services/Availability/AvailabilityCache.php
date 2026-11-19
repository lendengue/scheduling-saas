<?php

namespace App\Services\Availability;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Short-lived cache of computed slots, keyed by (tenant, service,
 * professional, date).
 *
 * Invalidation is per tenant: every key embeds a version number that is
 * bumped whenever something that affects availability changes, so stale
 * entries are simply never read again and expire on their own.
 */
final class AvailabilityCache
{
    public function __construct(private readonly Cache $cache) {}

    /**
     * @param  list<int|string>  $parts
     * @param  Closure(): list<array<string, mixed>>  $callback
     * @return list<array<string, mixed>>
     */
    public function remember(string $tenantId, array $parts, Closure $callback): array
    {
        $key = sprintf('availability:%s:v%d:%s', $tenantId, $this->version($tenantId), implode(':', $parts));

        /** @var list<array<string, mixed>> */
        return $this->cache->remember($key, (int) config('scheduling.availability_cache_seconds'), $callback);
    }

    public function flush(?string $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $this->cache->forever($this->versionKey($tenantId), $this->version($tenantId) + 1);
    }

    private function version(string $tenantId): int
    {
        return (int) $this->cache->get($this->versionKey($tenantId), 0);
    }

    private function versionKey(string $tenantId): string
    {
        return "availability:{$tenantId}:version";
    }
}
