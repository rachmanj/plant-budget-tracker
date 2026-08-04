<?php

namespace Database\Seeders;

use App\Models\ProjectCache;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['project_code' => 'MBL', 'project_name' => 'Mine Site MBL', 'is_active' => true],
            ['project_code' => 'SML', 'project_name' => 'Mine Site SML', 'is_active' => true],
        ];

        foreach ($projects as $project) {
            ProjectCache::updateOrCreate(
                ['project_code' => $project['project_code']],
                array_merge($project, ['synced_at' => now()])
            );
        }
    }
}
