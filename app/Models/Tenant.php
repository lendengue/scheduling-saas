<?php

namespace App\Models;

use App\Data\Tenants\BookingSettings;
use App\Models\Scopes\TenantScope;
use App\Support\Tenancy\TenantDomain;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property string $locale
 * @property string $currency
 * @property string|null $logo_path
 * @property string|null $brand_color
 * @property array<string, mixed> $booking_settings
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'slug', 'timezone', 'locale', 'currency', 'logo_path', 'brand_color', 'booking_settings', 'trial_ends_at'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'en',
        'currency' => 'BRL',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Default public booking rules.
     *
     * @return array<string, int|bool>
     */
    public static function defaultBookingSettings(): array
    {
        return (new BookingSettings)->toArray();
    }

    public function bookingSettings(): BookingSettings
    {
        return BookingSettings::fromArray($this->booking_settings);
    }

    /**
     * The tenant's users. The relation already constrains tenant_id, so the
     * current-tenant scope is removed to make it usable from any context.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Absolute URL on the tenant's subdomain.
     */
    public function url(string $path = '/'): string
    {
        return TenantDomain::tenantUrl($this->slug, $path);
    }
}
