<?php

namespace App\Jobs;

use App\Services\Budget\BudgetEngine;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class CarryForwardJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?Carbon $asOf = null,
    ) {
        $this->onQueue('budget');
    }

    public function handle(BudgetEngine $engine): void
    {
        $asOf = ($this->asOf ?? now())->copy()->timezone('Asia/Makassar');
        $monthKey = $asOf->format('Y-m');

        $lock = Cache::lock("carry_forward:{$monthKey}", 600);

        if (! $lock->get()) {
            return;
        }

        try {
            if (Cache::has("carry_forward:completed:{$monthKey}")) {
                return;
            }

            $engine->carryForward($asOf);

            Cache::put("carry_forward:completed:{$monthKey}", true, now()->addDays(35));
        } finally {
            $lock->release();
        }
    }
}
