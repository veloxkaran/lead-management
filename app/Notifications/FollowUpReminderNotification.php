<?php

namespace App\Notifications;

use App\Enums\ReminderType;
use App\Models\FollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpReminderNotification extends Notification
{
    use Queueable;

    public function __construct(protected FollowUp $followUp)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->followUp->reminder_type === ReminderType::Email
            ? ['mail', 'database']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->followUp->lead;

        return (new MailMessage)
            ->subject("Follow-up reminder: {$lead->company_name}")
            ->line("You have a follow-up scheduled for {$lead->company_name} on {$this->followUp->follow_up_date->format('M d, Y')} at {$this->followUp->follow_up_time}.")
            ->action('View Lead', route('leads.show', $lead))
            ->line('Thank you for staying on top of your pipeline.');
    }

    public function toArray(object $notifiable): array
    {
        $lead = $this->followUp->lead;

        return [
            'message' => "Follow-up reminder: {$lead->company_name} at {$this->followUp->follow_up_time}",
            'lead_id' => $lead->id,
            'follow_up_id' => $this->followUp->id,
        ];
    }
}
