<?php

namespace App\Support\Booking;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * What the public pages show about a business (branding included).
 */
final class PublicBusiness
{
    /**
     * @return array{name: string, brand_color: string|null, logo_url: string|null, timezone: string}
     */
    public static function from(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'brand_color' => $tenant->brand_color,
            'logo_url' => $tenant->logo_path === null ? null : Storage::disk('public')->url($tenant->logo_path),
            'timezone' => $tenant->timezone,
        ];
    }
}
