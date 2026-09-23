<?php

namespace App\Http\Controllers;

use App\Support\ProjectContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! ProjectContext::allowSwitch($user)) {
            return back()->with('error', 'Your account is bound to a single project and cannot switch project context.');
        }

        $validated = $request->validate([
            'project_code' => ['required', 'string', 'exists:projects_cache,project_code'],
        ]);

        $request->session()->put('current_project', $validated['project_code']);

        return back()->with('success', 'Project context updated.');
    }
}
