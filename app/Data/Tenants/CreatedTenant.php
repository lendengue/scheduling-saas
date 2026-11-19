<?php

namespace App\Data\Tenants;

use App\Models\Tenant;
use App\Models\User;

final readonly class CreatedTenant
{
    public function __construct(
        public Tenant $tenant,
        public User $owner,
    ) {}
}
