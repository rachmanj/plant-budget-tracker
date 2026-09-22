<?php

namespace App\Http\Middleware;

use App\Models\CancellationRequest;
use App\Models\InterchangeMap;
use App\Models\OverbudgetRequest;
use App\Models\PlantRequest;
use App\Models\RequestApproval;
use App\Models\TabulationBid;
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
            'canViewApprovals' => false,
            'canViewTabulationBids' => false,
            'canViewOverbudget' => false,
            'canViewCancellation' => false,
            'canViewInterchange' => false,
            'canCreatePlantRequest' => false,
            'viewSapDashboard' => false,
            'roles' => [],
        ];

        if ($user) {
            $roleNames = $user->getRoleNames();
            $rolesArray = $roleNames->all();
            $canViewApprovals = Gate::allows('viewAny', RequestApproval::class);

            $nav = [
                'pendingApprovals' => $canViewApprovals
                    ? RequestApproval::query()
                        ->where('decision', 'pending')
                        ->whereIn('required_role', $roleNames)
                        ->count()
                    : 0,
                'isApprover' => $canViewApprovals,
                'canViewApprovals' => $canViewApprovals,
                'canViewTabulationBids' => Gate::allows('viewAny', TabulationBid::class),
                'canViewOverbudget' => Gate::allows('viewAny', OverbudgetRequest::class),
                'canViewCancellation' => Gate::allows('viewAny', CancellationRequest::class),
                'canViewInterchange' => Gate::allows('viewAny', InterchangeMap::class),
                'canCreatePlantRequest' => Gate::allows('create', PlantRequest::class),
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
