<?php

namespace App\Console\Commands;

use App\Models\EmployeeMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ApplyEmployeeMovements extends Command
{
    protected $signature = 'movements:apply';

    protected $description = 'Apply approved employee movements whose effective date has arrived';

    public function handle(): int
    {
        $ids = EmployeeMovement::query()
            ->withoutGlobalScopes()
            ->where('status', 'approved')
            ->whereDate('effective_date', '<=', Carbon::today()->toDateString())
            ->pluck('id');

        $applied = 0;

        foreach ($ids as $id) {
            $movement = EmployeeMovement::query()->withoutGlobalScopes()->whereKey($id)->first();

            if ($movement !== null) {
                $movement->apply();
                $applied++;
            }
        }

        $this->info("Applied {$applied} movement(s).");

        return self::SUCCESS;
    }
}
