<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectCache;
use App\Services\Arkfleet\ArkfleetClient;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(ArkfleetClient $client): Response
    {
        try {
            $response = $client->getProjects(['active_only' => true]);
            $projects = $response['data'] ?? [];
            $stale = false;
        } catch (\Throwable $e) {
            $projects = ProjectCache::orderBy('project_name')->get()->map(fn ($p) => [
                'code' => $p->project_code,
                'name' => $p->project_name,
                'is_active' => $p->is_active,
            ])->all();
            $stale = true;
        }

        return Inertia::render('Admin/Projects', [
            'projects' => $projects,
            'cachedProjects' => ProjectCache::orderBy('project_name')->get(),
            'stale' => $stale,
        ]);
    }

    public function sync(ArkfleetClient $client): RedirectResponse
    {
        $response = $client->getProjects(['active_only' => true]);
        $projects = $response['data'] ?? [];

        foreach ($projects as $project) {
            $code = $project['code'] ?? $project['project_code'] ?? null;
            if (! $code) {
                continue;
            }

            ProjectCache::updateOrCreate(
                ['project_code' => $code],
                [
                    'project_name' => $project['name'] ?? $project['project_name'] ?? $code,
                    'is_active' => (bool) ($project['is_active'] ?? true),
                    'selectable_only' => (bool) ($project['selectable_only'] ?? false),
                    'raw_payload' => $project,
                    'synced_at' => now(),
                ]
            );
        }

        return back()->with('success', 'Daftar proyek berhasil disinkronkan dari ARKFLEET.');
    }
}
