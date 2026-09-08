<?php

namespace Database\Seeders;

use App\Models\ProjectCache;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $activeProjects = config('services.arkfleet.active_projects', ['021C', '025C', 'APS']);

        $projects = [
            ['project_code' => '000H', 'project_name' => 'Head Office', 'location' => 'Balikpapan'],
            ['project_code' => '001H', 'project_name' => 'ARKA Head Office', 'location' => 'Balikpapan'],
            ['project_code' => '005P', 'project_name' => 'Paser', 'location' => 'Paser'],
            ['project_code' => '017C', 'project_name' => 'Coal hauling 017C', 'location' => null],
            ['project_code' => '021C', 'project_name' => 'Coal hauling 021C', 'location' => null],
            ['project_code' => '022C', 'project_name' => 'Graha Panca Karsa', 'location' => null],
            ['project_code' => '025C', 'project_name' => 'Coal hauling 025C', 'location' => null],
            ['project_code' => 'APS', 'project_name' => 'APS Project', 'location' => null],
        ];

        ProjectCache::query()->whereIn('project_code', ['MBL', 'SML'])->delete();

        foreach ($projects as $project) {
            ProjectCache::updateOrCreate(
                ['project_code' => $project['project_code']],
                [
                    'project_name' => $project['project_name'],
                    'location' => $project['location'],
                    'is_active' => in_array($project['project_code'], $activeProjects, true),
                    'synced_at' => now(),
                ]
            );
        }
    }
}
