<?php

namespace App\Support\Tenancy;

/**
 * Maps hosts to tenants and builds absolute URLs for the central domain and
 * for tenant subdomains. The scheme and port come from APP_URL.
 */
final class TenantDomain
{
    public static function central(): string
    {
        return strtolower((string) config('tenancy.domain'));
    }

    public static function isCentral(string $host): bool
    {
        return strtolower($host) === self::central();
    }

    /**
     * Extract the tenant slug from a host such as "acme.scheduling-saas.test".
     * Returns null for the central domain and for unrelated hosts.
     */
    public static function slugFromHost(string $host): ?string
    {
        $host = strtolower($host);
        $suffix = '.'.self::central();

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        return $slug === '' || str_contains($slug, '.') ? null : $slug;
    }

    public static function centralUrl(string $path = '/'): string
    {
        return self::origin(self::central()).self::normalizePath($path);
    }

    public static function tenantUrl(string $slug, string $path = '/'): string
    {
        return self::origin($slug.'.'.self::central()).self::normalizePath($path);
    }

    private static function origin(string $host): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * The root path is dropped, like Laravel's URL generator does.
     */
    private static function normalizePath(string $path): string
    {
        $path = ltrim($path, '/');

        return $path === '' ? '' : '/'.$path;
    }
}
