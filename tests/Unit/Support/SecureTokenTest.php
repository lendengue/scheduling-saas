<?php

use App\Support\SecureToken;

test('generates a url-safe token from 32 random bytes', function () {
    $token = SecureToken::generate();

    expect($token->plain)
        ->toHaveLength(43)
        ->toMatch('/^[A-Za-z0-9_-]+$/');
});

test('stores only the sha-256 hash', function () {
    $token = SecureToken::generate();

    expect($token->hash)
        ->toBe(hash('sha256', $token->plain))
        ->not->toContain($token->plain);
});

test('generates a different token every time', function () {
    expect(SecureToken::generate()->plain)->not->toBe(SecureToken::generate()->plain);
});

test('matches the plain token against its hash', function () {
    $token = SecureToken::generate();

    expect(SecureToken::matches($token->plain, $token->hash))->toBeTrue()
        ->and(SecureToken::matches($token->plain.'x', $token->hash))->toBeFalse()
        ->and(SecureToken::matches('', $token->hash))->toBeFalse()
        ->and(SecureToken::matches(SecureToken::generate()->plain, $token->hash))->toBeFalse();
});
