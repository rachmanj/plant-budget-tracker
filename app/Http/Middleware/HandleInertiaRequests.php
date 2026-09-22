<?php

namespace App\Http\Middleware;

use App\Models\RequestApproval;
use App\Support\ApprovalChains;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        $nav = [
            'pendingApprovals' => 0,
            'isApprover' => false,
            'viewSapDashboard' => false,
            'roles' => [],
        ];

        if ($user) {
            $roleNames = $user->getRoleNames();
            $rolesArray = $roleNames->all();
            $approverRoles = ApprovalChains::approverRoles();
            $isApprover = (bool) array_intersect($rolesArray, $approverRoles);

            $nav = [
                'pendingApprovals' => $isApprover
                    ? RequestApproval::query()
                        ->where('decision', 'pending')
                        ->whereIn('required_role', $roleNames)
                        ->count()
                    : 0,
                'isApprover' => $isApprover,
                'viewSapDashboard' => Gate::allows('viewSapDashboard'),
                'roles' => $rolesArray,
            ];
        }

        return array_merge(parent::share($request), [
            'nav' => $nav,
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
            'features' => [
                'cannibal_beta' => config('features.cannibal_beta', false),
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
