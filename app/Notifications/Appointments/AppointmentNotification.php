<?php

namespace App\Notifications\Appointments;

use App\Models\Appointment;
use App\Models\Customer;
use App\Support\Booking\AppointmentSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for the appointment emails sent to the customer and to the
 * professional. Sent through the queue, retried with backoff; definitive
 * failures land in failed_jobs.
 */
abstract class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Appointment $appointment) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @return array{id: string, service: string, staff: string, date: string, time: string, ends_at_time: string, duration_minutes: int, price: string, status: string, status_label: string, starts_at: string}
     */
    protected function summary(): array
    {
        return AppointmentSummary::from($this->appointment, $this->appointment->tenant);
    }

    protected function isForCustomer(object $notifiable): bool
    {
        return $notifiable instanceof Customer;
    }

    /**
     * Emails go out under the business's name.
     */
    protected function message(): MailMessage
    {
        return (new MailMessage)->from((string) config('mail.from.address'), $this->appointment->tenant->name);
    }

    /**
     * @param  array{date: string, time: string, ends_at_time: string, service: string, staff: string, price: string}  $summary
     */
    protected function details(MailMessage $message, array $summary, bool $withStaff = true): MailMessage
    {
        $message->line("**{$summary['service']}**")
            ->line(ucfirst($summary['date']).", {$summary['time']} – {$summary['ends_at_time']}");

        if ($withStaff) {
            $message->line("With {$summary['staff']} · {$summary['price']}");
        }

        return $message;
    }
}
