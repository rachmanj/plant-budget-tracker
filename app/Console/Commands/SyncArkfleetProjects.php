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
        $activeProjects = config('services.arkfleet.active_projects', []);

        $synced = 0;

        foreach ($projects as $project) {
            $code = $project['project_code'] ?? $project['code'] ?? null;
            if (! $code) {
                continue;
            }

            ProjectCache::updateOrCreate(
                ['project_code' => $code],
                [
                    'project_name' => $project['name'] ?? $project['project_name'] ?? $project['bowheer'] ?? $code,
                    'location' => $project['location'] ?? null,
                    'is_active' => in_array($code, $activeProjects, true),
                    'selectable_only' => (bool) ($project['selectable_only'] ?? false),
                    'raw_payload' => $project,
                    'synced_at' => now(),
                ]
            );

            $synced++;
        }

        ProjectCache::query()->whereIn('project_code', ['MBL', 'SML'])->delete();

        $this->info("Synced {$synced} projects from ARKFLEET.");

        return self::SUCCESS;
    }
}
