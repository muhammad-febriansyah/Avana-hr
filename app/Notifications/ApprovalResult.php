<?php

namespace App\Notifications;

use App\Models\Approval;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalResult extends Notification
{
    public function __construct(
        public Approval $approval,
        public bool $approved,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->approved ? 'disetujui' : 'ditolak';

        return (new MailMessage)
            ->subject("Pengajuan Anda {$status}")
            ->line("Pengajuan Anda telah {$status}.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'approval_id' => $this->approval->id,
            'status' => $this->approved ? 'approved' : 'rejected',
        ];
    }
}
