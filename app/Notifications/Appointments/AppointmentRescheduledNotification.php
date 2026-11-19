<?php

namespace App\Notifications\Appointments;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

class AppointmentRescheduledNotification extends AppointmentNotification
{
    public function __construct(Appointment $appointment, public CarbonImmutable $previousStartsAt, public string $manageUrl)
    {
        parent::__construct($appointment);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $summary = $this->summary();
        $tenant = $this->appointment->tenant;
        /** @var CarbonImmutable $previous */
        $previous = $this->previousStartsAt->tz($tenant->timezone)->locale($tenant->locale);
        $previousLabel = ucfirst($previous->isoFormat('dddd, LL')).', '.$previous->format('H:i');

        if (! $this->isForCustomer($notifiable)) {
            return $this->details(
                $this->message()
                    ->subject("Rescheduled: {$summary['service']} now on {$summary['date']} at {$summary['time']}")
                    ->greeting('Appointment rescheduled')
                    ->line("The appointment with {$this->appointment->customer->name} on {$previousLabel} moved to:"),
                $summary,
                withStaff: false,
            )->action('Open the agenda', $tenant->url('/app/schedule'));
        }

        return $this->details(
            $this->message()
                ->subject("New time for your appointment at {$tenant->name}")
                ->greeting("Hi {$this->appointment->customer->name}!")
                ->line("Your appointment on {$previousLabel} moved to:"),
            $summary,
        )->action('Change or cancel', $this->manageUrl);
    }
}
