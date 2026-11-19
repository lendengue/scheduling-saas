<?php

use App\Support\Tenancy\TenantDomain;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'scheduling-saas.test', 'app.url' => 'http://scheduling-saas.test:8091']);
});

test('recognises the central domain', function () {
    expect(TenantDomain::isCentral('scheduling-saas.test'))->toBeTrue()
        ->and(TenantDomain::isCentral('SCHEDULING-SAAS.test'))->toBeTrue()
        ->and(TenantDomain::isCentral('acme.scheduling-saas.test'))->toBeFalse();
});

test('extracts the slug from a tenant host', function (string $host, ?string $slug) {
    expect(TenantDomain::slugFromHost($host))->toBe($slug);
})->with([
    ['acme.scheduling-saas.test', 'acme'],
    ['ACME.scheduling-saas.Test', 'acme'],
    ['scheduling-saas.test', null],
    ['a.b.scheduling-saas.test', null],
    ['acme.example.com', null],
    ['evilscheduling-saas.test', null],
]);

test('builds urls with the scheme and port of APP_URL', function () {
    expect(TenantDomain::tenantUrl('acme', 'app'))->toBe('http://acme.scheduling-saas.test:8091/app')
        ->and(TenantDomain::centralUrl())->toBe('http://scheduling-saas.test:8091')
        ->and(TenantDomain::tenantUrl('acme', '/'))->toBe('http://acme.scheduling-saas.test:8091');
});
