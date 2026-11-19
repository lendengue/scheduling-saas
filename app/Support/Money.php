<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Number;

/**
 * Prices are stored in cents and displayed in the tenant's currency and locale.
 */
final class Money
{
    public static function format(int $cents, Tenant $tenant): string
    {
        return (string) Number::currency($cents / 100, in: $tenant->currency, locale: $tenant->locale);
    }
}
