<?php

namespace App\Data\Tenants;

final readonly class NewTenantData
{
    public function __construct(
        public string $businessName,
        public string $slug,
        public string $timezone,
        public string $ownerName,
        public string $ownerEmail,
        public string $ownerPassword,
        public string $locale = 'en',
    ) {}
}
