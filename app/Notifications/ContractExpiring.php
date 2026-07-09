<?php

namespace App\Notifications;

use App\Models\EmployeeContract;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractExpiring extends Notification
{
    public function __construct(public EmployeeContract $contract, public int $daysLeft) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employeeName = $this->contract->employee->full_name;

        return (new MailMessage)
            ->subject("Kontrak berakhir dalam {$this->daysLeft} hari")
            ->line("Kontrak {$this->contract->contract_no} atas nama {$employeeName} akan berakhir pada {$this->contract->end_date?->toDateString()}.")
            ->line("Sisa {$this->daysLeft} hari — segera tindak lanjuti perpanjangan atau pengakhiran.")
            ->action('Lihat Karyawan', route('employees.show', $this->contract->employee_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contract_id' => $this->contract->id,
            'employee_id' => $this->contract->employee_id,
            'employee_name' => $this->contract->employee->full_name,
            'contract_no' => $this->contract->contract_no,
            'end_date' => $this->contract->end_date?->toDateString(),
            'days_left' => $this->daysLeft,
        ];
    }
}
