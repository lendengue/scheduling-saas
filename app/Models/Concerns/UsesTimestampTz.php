<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * For models whose date columns are PostgreSQL timestamptz.
 *
 * Eloquent's default format ("Y-m-d H:i:s") drops the timezone, so a Carbon
 * instance in the tenant's timezone would be stored as if it were UTC.
 * Writing the offset makes PostgreSQL store the exact instant, whatever the
 * timezone of the value or of the session.
 *
 * @mixin Model
 */
trait UsesTimestampTz
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:sP';
    }
}
