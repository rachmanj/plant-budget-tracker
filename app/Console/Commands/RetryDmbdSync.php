<?php

namespace App\Console\Commands;

use App\Jobs\SyncDmbdStatusToArkfleet;
use App\Models\DmbdEntry;
use Illuminate\Console\Command;

class RetryDmbdSync extends Command
{
    protected $signature = 'dmbd:retry-sync';

    protected $description = 'Retry failed DMBD status syncs to ARKFLEET';

    public function handle(): int
    {
        $entries = DmbdEntry::query()->where('synced_to_arkfleet', false)->get();

        foreach ($entries as $entry) {
            SyncDmbdStatusToArkfleet::dispatch($entry->id);
        }

        $this->info("Dispatched {$entries->count()} DMBD sync jobs.");

        return self::SUCCESS;
    }
}
