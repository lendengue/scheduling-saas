<?php

namespace App\Enums;

/**
 * Where a booking comes from. Customers on the public page must pick a slot
 * offered by the availability calculator; the team booking from the panel
 * may place an appointment anywhere that does not overlap another one.
 */
enum BookingChannel: string
{
    case Public = 'public';
    case Panel = 'panel';
}
