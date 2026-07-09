<?php

namespace App\Console\Commands;

use App\Actions\Attendance\BuildAttendanceSummaries;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RebuildAttendanceSummaries extends Command
{
    protected $signature = 'attendance:rebuild {date?}';

    protected $description = 'Rebuild daily attendance summaries from events (defaults to yesterday)';

    public function handle(BuildAttendanceSummaries $builder): int
    {
        $date = $this->argument('date') !== null
            ? Carbon::parse((string) $this->argument('date'))
            : Carbon::yesterday();

        $total = 0;

        Tenant::query()->chunkById(50, function (Collection $tenants) use ($builder, $date, &$total): void {
            foreach ($tenants as $tenant) {
                $total += $builder->handle((int) $tenant->id, $date);
            }
        });

        $this->info("Rebuilt {$total} attendance summary/summaries for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
