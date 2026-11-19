<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Availability Cache
    |--------------------------------------------------------------------------
    |
    | Computed slots are cached per (tenant, service, professional, date) for
    | a short time, and the whole tenant's cache is invalidated whenever an
    | appointment, time off, working hour, professional or service changes.
    |
    */

    'availability_cache_seconds' => 60,

    /*
    |--------------------------------------------------------------------------
    | Public Booking
    |--------------------------------------------------------------------------
    */

    // Bookings accepted per IP address per hour on the public page.
    'public_bookings_per_hour' => 10,

];
