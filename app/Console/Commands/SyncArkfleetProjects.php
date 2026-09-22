<?php

namespace App\Console\Commands;

use App\Models\ProjectCache;
use App\Services\Arkfleet\ArkfleetClient;
use Illuminate\Console\Command;

class SyncArkfleetProjects extends Command
{
    protected $signature = 'arkfleet:sync-projects';

    protected $description = 'Sync project list from ARKFLEET (ns15 legacy API) into projects_cache';

    public function handle(ArkfleetClient $client): int
    {
        $response = $client->getProjects();
        $projects = $response['data'] ?? [];

        $counts = ProjectCache::syncFromArkfleet($projects);

        $this->info("Synced from ARKFLEET: {$counts['created']} new, {$counts['updated']} updated.");

        return self::SUCCESS;
    }
}
