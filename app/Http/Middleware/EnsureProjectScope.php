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
            setPermissionsTeamId($user->project_code_scope ?? '');
        }

        return $next($request);
    }
}
