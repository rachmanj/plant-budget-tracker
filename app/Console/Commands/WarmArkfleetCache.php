<?php

namespace App\Console\Commands;

use App\Models\ProjectCache;
use App\Services\Arkfleet\EquipmentCache;
use Illuminate\Console\Command;

class WarmArkfleetCache extends Command
{
    protected $signature = 'arkfleet:warm-cache';

    protected $description = 'Pre-warm ARKFLEET equipment cache for active projects';

    public function handle(EquipmentCache $cache): int
    {
        $projects = ProjectCache::where('is_active', true)->pluck('project_code');

        if ($projects->isEmpty()) {
            $projects = collect(['MBL', 'SML']);
        }

        foreach ($projects as $projectCode) {
            $this->info("Warming equipment cache for {$projectCode}...");
            $cache->list(['project_code' => $projectCode, 'is_active' => true]);
            $cache->stats($projectCode);
        }

        $this->info('ARKFLEET cache warm-up selesai.');

        return self::SUCCESS;
    }
}
