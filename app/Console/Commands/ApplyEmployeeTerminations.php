<?php

namespace App\Console\Commands;

use App\Models\EmployeeTermination;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ApplyEmployeeTerminations extends Command
{
    protected $signature = 'terminations:apply';

    protected $description = 'Deactivate employees whose termination effective date has arrived';

    public function handle(): int
    {
        $ids = EmployeeTermination::query()
            ->withoutGlobalScopes()
            ->where('status', '!=', 'completed')
            ->whereDate('effective_date', '<=', Carbon::today()->toDateString())
            ->pluck('id');

        $applied = 0;

        foreach ($ids as $id) {
            $termination = EmployeeTermination::query()->withoutGlobalScopes()->whereKey($id)->first();

            if ($termination !== null) {
                $termination->apply();
                $applied++;
            }
        }

        $this->info("Applied {$applied} termination(s).");

        return self::SUCCESS;
    }
}
