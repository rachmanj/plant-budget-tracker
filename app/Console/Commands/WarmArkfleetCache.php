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
        $this->info('Warming full equipment list...');
        $cache->list([]);
        $cache->stats(null);

        $projects = ProjectCache::where('is_active', true)->pluck('project_code');

        if ($projects->isEmpty()) {
            $projects = collect(config('services.arkfleet.active_projects', []));
        }

        foreach ($projects as $projectCode) {
            $this->info("Warming equipment cache for {$projectCode}...");
            $cache->list(['project_code' => $projectCode]);
            $cache->stats($projectCode);
        }

        $this->info('ARKFLEET cache warm-up selesai.');

        return self::SUCCESS;
    }
}
