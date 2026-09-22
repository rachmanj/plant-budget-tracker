<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCache extends Model
{
    protected $table = 'projects_cache';

    protected $fillable = [
        'project_code',
        'project_name',
        'location',
        'is_active',
        'selectable_only',
        'raw_payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'selectable_only' => 'boolean',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $projects
     * @return array{created: int, updated: int}
     */
    public static function syncFromArkfleet(array $projects): array
    {
        $activeProjects = config('services.arkfleet.active_projects', []);
        $created = 0;
        $updated = 0;

        foreach ($projects as $project) {
            $code = $project['project_code'] ?? $project['code'] ?? null;
            if (! is_string($code) || $code === '') {
                continue;
            }

            $payload = [
                'project_name' => $project['name'] ?? $project['project_name'] ?? $project['bowheer'] ?? $code,
                'location' => $project['location'] ?? null,
                'raw_payload' => $project,
                'synced_at' => now(),
            ];

            $existing = static::query()->where('project_code', $code)->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                static::query()->create(array_merge($payload, [
                    'project_code' => $code,
                    'is_active' => in_array($code, $activeProjects, true),
                    'selectable_only' => (bool) ($project['selectable_only'] ?? false),
                ]));
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
