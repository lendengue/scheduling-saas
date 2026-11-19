<?php

namespace App\Services\Availability;

use Carbon\CarbonImmutable;

/**
 * A bookable start time. Times are UTC; the label is the start in the
 * tenant's wall-clock time ("14:30").
 */
final readonly class Slot
{
    /**
     * @param  list<int>  $staffProfileIds  professionals free at this time
     */
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public array $staffProfileIds,
        public string $label,
    ) {}

    /**
     * @return array{starts_at: string, ends_at: string, staff_profile_ids: list<int>, label: string}
     */
    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt->toIso8601ZuluString(),
            'ends_at' => $this->endsAt->toIso8601ZuluString(),
            'staff_profile_ids' => $this->staffProfileIds,
            'label' => $this->label,
        ];
    }

    /**
     * @param  array{starts_at: string, ends_at: string, staff_profile_ids: list<int>, label: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            CarbonImmutable::parse($data['starts_at'])->utc(),
            CarbonImmutable::parse($data['ends_at'])->utc(),
            $data['staff_profile_ids'],
            $data['label'],
        );
    }
}
