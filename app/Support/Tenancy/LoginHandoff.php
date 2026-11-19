<?php

namespace App\Support\Tenancy;

use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Illuminate\Contracts\Cache\Repository as Cache;
use InvalidArgumentException;

/**
 * Logs a user into their tenant subdomain right after an action on the
 * central domain (tenant registration).
 *
 * Session cookies are host-only, so the central session cannot be reused on
 * the subdomain. Instead, a single-use token valid for a few seconds is
 * stored (hashed) in the cache, and the subdomain exchanges it for a session.
 */
final class LoginHandoff
{
    private const string CACHE_PREFIX = 'login-handoff:';

    public function __construct(
        private readonly Cache $cache,
        private readonly CurrentTenant $currentTenant,
    ) {}

    /**
     * Issue a handoff and return the absolute URL that redeems it.
     */
    public function issue(User $user, string $redirectTo = '/app'): string
    {
        $tenant = $user->tenant ?? throw new InvalidArgumentException('Only tenant users can be handed off.');
        $token = SecureToken::generate();

        $this->cache->put(self::CACHE_PREFIX.$token->hash, [
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant->getKey(),
            'redirect_to' => '/'.ltrim($redirectTo, '/'),
        ], (int) config('tenancy.login_handoff_seconds'));

        return $tenant->url('/auth/handoff/'.$token->plain);
    }

    /**
     * Consume a handoff issued for the current tenant.
     *
     * @return array{user: User, redirect_to: string}|null
     */
    public function redeem(string $plainToken): ?array
    {
        $payload = $this->cache->pull(self::CACHE_PREFIX.SecureToken::hash($plainToken));

        if (! is_array($payload) || $payload['tenant_id'] !== $this->currentTenant->id()) {
            return null;
        }

        $user = User::query()->whereKey($payload['user_id'])->first();

        return $user === null ? null : ['user' => $user, 'redirect_to' => (string) $payload['redirect_to']];
    }
}
