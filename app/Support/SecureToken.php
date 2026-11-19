<?php

namespace App\Support;

/**
 * A random secret sent to a user (invitation links, login handoffs and, later,
 * appointment management links). Only the SHA-256 hash is ever stored.
 */
final readonly class SecureToken
{
    private function __construct(
        public string $plain,
        public string $hash,
    ) {}

    /**
     * Generate a token from 32 random bytes, encoded as URL-safe base64.
     */
    public static function generate(): self
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new self($plain, self::hash($plain));
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Compare a plain token with a stored hash in constant time.
     */
    public static function matches(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::hash($plain));
    }
}
