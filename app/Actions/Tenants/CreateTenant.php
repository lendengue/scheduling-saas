<?php

namespace App\Actions\Tenants;

use App\Data\Tenants\CreatedTenant;
use App\Data\Tenants\NewTenantData;
use App\Enums\Role;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * Creates a tenant, its owner and the owner role in a single transaction,
 * and starts the trial.
 */
class CreateTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(NewTenantData $data): CreatedTenant
    {
        return DB::transaction(function () use ($data): CreatedTenant {
            $tenant = Tenant::create([
                'name' => $data->businessName,
                'slug' => $data->slug,
                'timezone' => $data->timezone,
                'locale' => $data->locale,
                'booking_settings' => Tenant::defaultBookingSettings(),
                // Generic Cashier trial on the tenant; plans arrive in Phase 3.
                'trial_ends_at' => now()->addDays((int) config('tenancy.trial_days')),
            ]);

            $owner = $this->currentTenant->runAs($tenant, function () use ($data): User {
                $owner = User::create([
                    'name' => $data->ownerName,
                    'email' => $data->ownerEmail,
                    'password' => $data->ownerPassword,
                ]);

                $owner->assignRole(Role::Owner);

                // Most small businesses start with the owner taking the bookings.
                StaffProfile::forUser($owner, bookable: true);

                return $owner;
            });

            return new CreatedTenant($tenant, $owner);
        });
    }
}
