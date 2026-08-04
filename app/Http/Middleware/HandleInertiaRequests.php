<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends \Inertia\Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'division' => $user->division,
                    'project_code_scope' => $user->project_code_scope,
                    'roles' => $user->getRoleNames(),
                ] : null,
                'can' => $user ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'appName' => config('app.name'),
        ]);

        return parent::handle($request, $next);
    }
}
