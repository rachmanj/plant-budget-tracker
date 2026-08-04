<?php

namespace App\Console\Commands;

use App\Jobs\CarryForwardJob;
use App\Services\Budget\BudgetEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunCarryForward extends Command
{
    protected $signature = 'budget:carry-forward {--month= : YYYY-MM month to process (defaults to current)}';

    protected $description = 'Run budget carry-forward for ended open periods';

    public function handle(BudgetEngine $engine): int
    {
        $asOf = $this->option('month')
            ? Carbon::parse($this->option('month').'-01', 'Asia/Makassar')->startOfMonth()->addMonth()
            : now('Asia/Makassar');

        if ($this->option('month')) {
            $count = $engine->carryForward($asOf);
            $this->info("Carry-forward processed {$count} period(s).");

            return self::SUCCESS;
        }

        CarryForwardJob::dispatch($asOf);
        $this->info('CarryForwardJob dispatched to budget queue.');

        return self::SUCCESS;
    }
}
