<?php

namespace App\Notifications\Appointments;

use App\Models\Appointment;
use Illuminate\Notifications\Messages\MailMessage;

class AppointmentCancelledNotification extends AppointmentNotification
{
    public function __construct(Appointment $appointment, public bool $byCustomer)
    {
        parent::__construct($appointment);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $summary = $this->summary();
        $tenant = $this->appointment->tenant;
        $reason = $this->appointment->cancellation_reason;

        if (! $this->isForCustomer($notifiable)) {
            return $this->details(
                $this->message()
                    ->subject("Cancelled: {$summary['service']} on {$summary['date']} at {$summary['time']}")
                    ->greeting('Appointment cancelled')
                    ->line($this->byCustomer
                        ? "{$this->appointment->customer->name} cancelled this appointment:"
                        : "This appointment with {$this->appointment->customer->name} was cancelled:"),
                $summary,
                withStaff: false,
            )->when($reason !== null, fn (MailMessage $message) => $message->line("Reason: {$reason}"));
        }

        return $this->details(
            $this->message()
                ->subject("Your appointment at {$tenant->name} was cancelled")
                ->greeting("Hi {$this->appointment->customer->name},")
                ->line($this->byCustomer
                    ? 'As requested, this appointment is cancelled:'
                    : "{$tenant->name} had to cancel this appointment:"),
            $summary,
        )
            ->when(! $this->byCustomer && $reason !== null, fn (MailMessage $message) => $message->line("Reason: {$reason}"))
            ->action('Book again', $tenant->url('/book'));
    }
}
