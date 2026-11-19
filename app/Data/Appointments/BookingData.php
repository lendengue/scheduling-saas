<?php

namespace App\Data\Appointments;

use App\Enums\BookingChannel;
use App\Models\Service;
use App\Models\StaffProfile;
use Carbon\CarbonImmutable;

final readonly class BookingData
{
    /**
     * @param  StaffProfile|null  $staff  null means "any professional"
     * @param  CarbonImmutable  $startsAt  the chosen slot (any timezone)
     */
    public function __construct(
        public Service $service,
        public ?StaffProfile $staff,
        public CarbonImmutable $startsAt,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone = null,
        public ?string $notes = null,
        public BookingChannel $channel = BookingChannel::Public,
    ) {}
}
