<?php

namespace App\Notifications\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\Calendar\AppointmentIcs;
use Illuminate\Notifications\Messages\MailMessage;

class AppointmentBookedNotification extends AppointmentNotification
{
    public function __construct(Appointment $appointment, public string $manageUrl)
    {
        parent::__construct($appointment);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $summary = $this->summary();
        $tenant = $this->appointment->tenant;
        $pending = $this->appointment->status === AppointmentStatus::Pending;

        if (! $this->isForCustomer($notifiable)) {
            return $this->details(
                $this->message()
                    ->subject("New booking: {$summary['service']} on {$summary['date']} at {$summary['time']}")
                    ->greeting('New booking')
                    ->line("{$this->appointment->customer->name} booked with you.")
                    ->when($pending, fn (MailMessage $message) => $message->line('It is waiting for approval.')),
                $summary,
                withStaff: false,
            )->action('Open the agenda', $tenant->url('/app/schedule'));
        }

        return $this->details(
            $this->message()
                ->subject($pending
                    ? "Booking request received – {$tenant->name}"
                    : "Your booking at {$tenant->name} is confirmed")
                ->greeting("Hi {$this->appointment->customer->name}!")
                ->line($pending
                    ? "{$tenant->name} received your request and will confirm it shortly:"
                    : 'Your appointment is booked:'),
            $summary,
        )
            ->action('Change or cancel', $this->manageUrl)
            ->line('Keep this email: the button above is your private link to manage the booking.')
            ->attachData(AppointmentIcs::build($this->appointment, $this->manageUrl), 'appointment.ics', ['mime' => 'text/calendar']);
    }
}
