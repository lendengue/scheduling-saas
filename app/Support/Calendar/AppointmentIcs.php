<?php

namespace App\Support\Calendar;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

/**
 * Builds an iCalendar (RFC 5545) file for an appointment, so customers can
 * add it to Google Calendar, Apple Calendar or Outlook.
 */
final class AppointmentIcs
{
    public static function build(Appointment $appointment, ?string $manageUrl = null): string
    {
        $tenant = $appointment->tenant;
        $description = 'With '.$appointment->staffProfile->user->name.'.';

        if ($manageUrl !== null) {
            $description .= "\nCancel or reschedule: {$manageUrl}";
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//scheduling-saas//Booking//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$appointment->id.'@scheduling-saas',
            'DTSTAMP:'.self::date(CarbonImmutable::now()),
            'DTSTART:'.self::date($appointment->starts_at),
            'DTEND:'.self::date($appointment->serviceEndsAt()),
            'SUMMARY:'.self::text($appointment->service->name.' at '.$tenant->name),
            'DESCRIPTION:'.self::text($description),
            'LOCATION:'.self::text($tenant->name),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function date(CarbonImmutable $date): string
    {
        return $date->utc()->format('Ymd\THis\Z');
    }

    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /**
     * Lines longer than 75 octets are folded with CRLF + space.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];

        while (strlen($line) > 75) {
            $cut = 75;

            // Never split a multibyte UTF-8 character.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            $chunks[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }

        $chunks[] = $line;

        return implode("\r\n", $chunks);
    }
}
