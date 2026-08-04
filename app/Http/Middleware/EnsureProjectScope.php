<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $projectCode = $request->route('project_code')
                ?? $request->input('project_code')
                ?? session('current_project')
                ?? $user->project_code_scope;

            if ($projectCode) {
                setPermissionsTeamId($projectCode);
            }
        }

        return $next($request);
    }
}
