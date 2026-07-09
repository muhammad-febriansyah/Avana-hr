<?php

namespace App\Notifications;

use App\Models\Approval;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequested extends Notification
{
    public function __construct(public Approval $approval) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Persetujuan menunggu tindakan Anda')
            ->line('Ada pengajuan yang menunggu persetujuan Anda di AvanaHR.')
            ->action('Buka Persetujuan', url('/approvals'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'approval_id' => $this->approval->id,
            'approvable_type' => class_basename($this->approval->approvable_type),
            'step' => $this->approval->current_step,
        ];
    }
}
