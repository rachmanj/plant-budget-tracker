<?php

namespace Tests\Feature\Access;

use App\Services\Arkfleet\EquipmentCache;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ModulePageAccessTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_approvals_index_allows_approver_and_denies_non_approver(): void
    {
        $this->actingAsProject($this->makeUserWithRole('project_manager'))
            ->withoutVite()
            ->get('/approvals')
            ->assertOk();

        $this->actingAsProject($this->makeUserWithRole('planner'))
            ->withoutVite()
            ->get('/approvals')
            ->assertForbidden();
    }

    public function test_tabulation_bids_index_allows_buyer_and_denies_mechanic(): void
    {
        $this->actingAsProject($this->makeUserWithRole('buyer'))
            ->withoutVite()
            ->get('/tabulation-bids')
            ->assertOk();

        $this->actingAsProject($this->makeUserWithRole('mechanic'))
            ->withoutVite()
            ->get('/tabulation-bids')
            ->assertForbidden();
    }

    public function test_overbudget_index_allows_planner_and_denies_buyer(): void
    {
        $this->actingAsProject($this->makeUserWithRole('planner'))
            ->withoutVite()
            ->get('/overbudget')
            ->assertOk();

        $this->actingAsProject($this->makeUserWithRole('buyer'))
            ->withoutVite()
            ->get('/overbudget')
            ->assertForbidden();
    }

    public function test_cancellation_index_allows_planner_and_denies_logistic_pic(): void
    {
        $this->actingAsProject($this->makeUserWithRole('planner'))
            ->withoutVite()
            ->get('/cancellation')
            ->assertOk();

        $this->actingAsProject($this->makeUserWithRole('logistic_pic'))
            ->withoutVite()
            ->get('/cancellation')
            ->assertForbidden();
    }

    public function test_interchange_index_allows_plant_manager_and_aml_manager_and_denies_mechanic(): void
    {
        $this->actingAsProject($this->makeUserWithRole('plant_manager'))
            ->withoutVite()
            ->get('/interchange')
            ->assertOk();

        $this->actingAsProject($this->makeUserWithRole('aml_manager'))
            ->withoutVite()
            ->get('/interchange')
            ->assertOk();

        $this->actingAsProject($this->makeUserWithRole('mechanic'))
            ->withoutVite()
            ->get('/interchange')
            ->assertForbidden();
    }

    public function test_plant_request_create_allows_planner_and_denies_buyer(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => [], 'stale' => false]);
        });

        $this->actingAsProject($this->makeUserWithRole('planner'))
            ->withoutVite()
            ->get('/plant-requests/create')
            ->assertOk();

        $this->actingAsProject($this->makeUserWithRole('buyer'))
            ->withoutVite()
            ->get('/plant-requests/create')
            ->assertForbidden();
    }

    public function test_plant_request_store_returns_403_for_unauthorized_role(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $this->actingAsProject($buyer)
            ->post('/plant-requests', [])
            ->assertForbidden();
    }

    public function test_nav_props_reflect_page_access_for_planner_and_project_manager(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => []]);
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->withoutVite()
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('nav.canCreatePlantRequest', true)
                ->where('nav.canViewApprovals', false)
                ->where('nav.canViewTabulationBids', false)
                ->where('nav.canViewOverbudget', true)
                ->where('nav.canViewCancellation', true)
                ->where('nav.canViewInterchange', false)
            );

        $projectManager = $this->makeUserWithRole('project_manager');

        $this->actingAsProject($projectManager)
            ->withoutVite()
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('nav.canCreatePlantRequest', false)
                ->where('nav.canViewApprovals', true)
                ->where('nav.canViewTabulationBids', false)
                ->where('nav.canViewOverbudget', true)
                ->where('nav.canViewCancellation', true)
                ->where('nav.canViewInterchange', true)
            );
    }

    public function test_it_manager_can_open_all_governed_module_indexes(): void
    {
        $itManager = $this->makeUserWithRole('it_manager');

        $this->actingAsProject($itManager)
            ->withoutVite()
            ->get('/approvals')
            ->assertOk();

        $this->actingAsProject($itManager)
            ->get('/tabulation-bids')
            ->assertOk();

        $this->actingAsProject($itManager)
            ->get('/overbudget')
            ->assertOk();

        $this->actingAsProject($itManager)
            ->get('/cancellation')
            ->assertOk();

        $this->actingAsProject($itManager)
            ->get('/interchange')
            ->assertOk();
    }
}
