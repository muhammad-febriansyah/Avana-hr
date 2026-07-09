<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\EmployeeContract;
use App\Models\User;
use App\Notifications\ContractExpiring;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class RemindExpiringContracts extends Command
{
    protected $signature = 'contracts:remind';

    protected $description = 'Notify HR of contracts nearing expiry (H-30/14/7) and mark past-due contracts expired';

    /**
     * Days-before-expiry thresholds that trigger a reminder.
     *
     * @var list<int>
     */
    private array $thresholds = [30, 14, 7];

    public function handle(PermissionRegistrar $registrar): int
    {
        $today = Carbon::today();
        $reminded = 0;

        foreach ($this->thresholds as $daysLeft) {
            EmployeeContract::query()
                ->withoutGlobalScopes()
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->whereDate('end_date', $today->copy()->addDays($daysLeft)->toDateString())
                ->whereHas('employee')
                ->with('employee:id,tenant_id,full_name')
                ->chunkById(100, function (Collection $contracts) use ($daysLeft, $registrar, &$reminded): void {
                    foreach ($contracts as $contract) {
                        $recipients = $this->hrRecipients($registrar, (int) $contract->tenant_id);

                        if ($recipients->isNotEmpty()) {
                            Notification::send($recipients, new ContractExpiring($contract, $daysLeft));
                            $reminded++;
                        }
                    }
                });
        }

        $expired = EmployeeContract::query()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today->toDateString())
            ->update(['status' => 'expired']);

        $this->info("Reminded {$reminded} contract(s); marked {$expired} contract(s) expired.");

        return self::SUCCESS;
    }

    /**
     * HR recipients (Company Admin + HR Admin) for a tenant.
     *
     * @return Collection<int, User>
     */
    private function hrRecipients(PermissionRegistrar $registrar, int $tenantId): Collection
    {
        $registrar->setPermissionsTeamId($tenantId);

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->role([Role::CompanyAdmin->value, Role::HrAdmin->value])
            ->get();

        return $users;
    }
}
