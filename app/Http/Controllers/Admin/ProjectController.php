<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectCache;
use App\Services\Arkfleet\ArkfleetClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(ArkfleetClient $client): Response
    {
        $arkfleetReachable = true;

        try {
            $client->getProjects();
        } catch (\Throwable) {
            $arkfleetReachable = false;
        }

        $projects = ProjectCache::query()
            ->orderBy('project_name')
            ->get()
            ->map(fn (ProjectCache $p) => [
                'project_code' => $p->project_code,
                'project_name' => $p->project_name,
                'location' => $p->location,
                'is_active' => $p->is_active,
                'synced_at' => $p->synced_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $lastSyncedAt = ProjectCache::query()->max('synced_at');

        return Inertia::render('Admin/Projects', [
            'projects' => $projects,
            'lastSyncedAt' => $lastSyncedAt ? (string) $lastSyncedAt : null,
            'arkfleetReachable' => $arkfleetReachable,
        ]);
    }

    public function update(Request $request, string $projectCode): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $project = ProjectCache::query()
            ->where('project_code', $projectCode)
            ->firstOrFail();

        $project->update(['is_active' => $validated['is_active']]);

        $message = $validated['is_active']
            ? "Proyek {$projectCode} diaktifkan."
            : "Proyek {$projectCode} dinonaktifkan.";

        return back()->with('success', $message);
    }

    public function sync(ArkfleetClient $client): RedirectResponse
    {
        $response = $client->getProjects();
        $projects = $response['data'] ?? [];

        $counts = ProjectCache::syncFromArkfleet($projects);

        return back()->with(
            'success',
            "Sinkronisasi selesai: {$counts['created']} proyek baru, {$counts['updated']} diperbarui."
        );
    }
}
