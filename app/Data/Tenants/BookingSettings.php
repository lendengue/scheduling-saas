<?php

namespace App\Data\Tenants;

/**
 * The tenant's public booking rules (tenants.booking_settings), with defaults
 * for missing keys.
 */
final readonly class BookingSettings
{
    public function __construct(
        public int $slotInterval = 15,
        public int $minNoticeMinutes = 120,
        public int $maxAdvanceDays = 60,
        public int $cancellationNoticeHours = 24,
        public bool $requiresApproval = false,
    ) {}

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function fromArray(?array $settings): self
    {
        $settings ??= [];
        $defaults = new self;

        return new self(
            slotInterval: max(5, (int) ($settings['slot_interval'] ?? $defaults->slotInterval)),
            minNoticeMinutes: max(0, (int) ($settings['min_notice_minutes'] ?? $defaults->minNoticeMinutes)),
            maxAdvanceDays: max(0, (int) ($settings['max_advance_days'] ?? $defaults->maxAdvanceDays)),
            cancellationNoticeHours: max(0, (int) ($settings['cancellation_notice_hours'] ?? $defaults->cancellationNoticeHours)),
            requiresApproval: (bool) ($settings['requires_approval'] ?? $defaults->requiresApproval),
        );
    }

    /**
     * @return array{slot_interval: int, min_notice_minutes: int, max_advance_days: int, cancellation_notice_hours: int, requires_approval: bool}
     */
    public function toArray(): array
    {
        return [
            'slot_interval' => $this->slotInterval,
            'min_notice_minutes' => $this->minNoticeMinutes,
            'max_advance_days' => $this->maxAdvanceDays,
            'cancellation_notice_hours' => $this->cancellationNoticeHours,
            'requires_approval' => $this->requiresApproval,
        ];
    }
}
