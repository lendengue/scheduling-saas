<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
        };
    }

    /**
     * Statuses that hold time in the agenda (and in the exclusion constraint).
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * @return list<string>
     */
    public static function blockingValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::blocking());
    }

    public function isBlocking(): bool
    {
        return in_array($this, self::blocking(), true);
    }

    /**
     * The lifecycle: pending → confirmed → completed | no_show, and any
     * active appointment can be cancelled.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($next, [self::Completed, self::NoShow, self::Cancelled], true),
            self::Completed, self::Cancelled, self::NoShow => false,
        };
    }
}
