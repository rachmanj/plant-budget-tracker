<?php

namespace App\Http\Middleware;

use App\Models\CancellationRequest;
use App\Models\InterchangeMap;
use App\Models\OverbudgetRequest;
use App\Models\PlantRequest;
use App\Models\ProjectCache;
use App\Models\RequestApproval;
use App\Models\TabulationBid;
use App\Support\ProjectContext;
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
            'canSwitchProject' => false,
            'currentProject' => '',
            'currentProjectName' => '',
            'activeProjects' => [],
        ];

        if ($user) {
            $roleNames = $user->getRoleNames();
            $rolesArray = $roleNames->all();
            $canViewApprovals = Gate::allows('viewAny', RequestApproval::class);

            $currentProject = ProjectContext::resolve($request);

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
                'canSwitchProject' => ProjectContext::allowSwitch($user),
                'currentProject' => $currentProject,
                'currentProjectName' => self::projectNameForCode($currentProject),
                'activeProjects' => ProjectCache::query()
                    ->where('is_active', true)
                    ->orderBy('project_code')
                    ->get(['project_code', 'project_name'])
                    ->map(fn (ProjectCache $p) => [
                        'project_code' => $p->project_code,
                        'project_name' => $p->project_name,
                    ])
                    ->values()
                    ->all(),
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

    private static function projectNameForCode(string $projectCode): string
    {
        if ($projectCode === '' || $projectCode === 'all') {
            return $projectCode;
        }

        return ProjectCache::query()
            ->where('project_code', $projectCode)
            ->value('project_name') ?? $projectCode;
    }
}
