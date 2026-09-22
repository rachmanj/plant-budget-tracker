<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\Arkfleet\EquipmentCache;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_dashboard_returns_ok_with_zeroed_metrics_when_transactional_tables_are_empty(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => []]);
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard', false)
                ->where('metrics.budget.allocated', '0.00')
                ->where('metrics.requests.draft', 0)
                ->where('metrics.procurement.bidsAwaitingReview', 0)
                ->where('metrics.pending.overbudget', 0)
                ->where('metrics.dmbd.rfu', 0)
                ->where('metrics.dmbd.available', true)
            );
    }

    public function test_dashboard_marks_dmbd_unavailable_when_arkfleet_fails(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andThrow(new \RuntimeException('ARKFLEET unreachable'));
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard', false)
                ->where('metrics.dmbd.available', false)
                ->where('metrics.dmbd.rfu', 0)
                ->where('metrics.dmbd.unitsActive', null)
            );
    }

    public function test_dashboard_never_calls_arkfleet_when_user_cannot_view_dmbd(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldNotReceive('list');
        });

        $buyer = $this->makeUserWithRole('buyer');

        $response = $this->actingAsProject($buyer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('metrics.dmbd.available', false));

        $this->assertFalse($response->inertiaProps('can')['dmbd.view']);
    }

    public function test_can_flags_hide_budget_section_for_user_without_permission(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => []]);
        });

        $bareUser = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => 'MBL',
        ]);
        setPermissionsTeamId('MBL');

        $response = $this->actingAs($bareUser)
            ->withoutVite()
            ->withSession(['current_project' => 'MBL'])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard', false));

        $can = $response->inertiaProps('can');
        $this->assertFalse($can['budget.view']);
        $this->assertFalse($can['plant_request.create']);
    }

    public function test_can_flags_true_for_permitted_planner(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => []]);
        });

        $planner = $this->makeUserWithRole('planner');

        $response = $this->actingAsProject($planner)
            ->get('/dashboard')
            ->assertOk();

        $can = $response->inertiaProps('can');
        $this->assertTrue($can['budget.view']);
        $this->assertTrue($can['plant_request.create']);
        $this->assertTrue($can['dmbd.view']);
    }

    public function test_approver_without_creation_permission_still_sees_requests_section(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => []]);
        });

        $plantManager = $this->makeUserWithRole('plant_manager');

        $response = $this->actingAsProject($plantManager)
            ->get('/dashboard')
            ->assertOk();

        $can = $response->inertiaProps('can');
        $this->assertFalse($can['plant_request.create']);
        $this->assertTrue($response->inertiaProps('nav.isApprover'));
    }

    public function test_non_approver_without_creation_permission_does_not_see_requests_section(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => []]);
        });

        $logisticPic = $this->makeUserWithRole('logistic_pic');

        $response = $this->actingAsProject($logisticPic)
            ->get('/dashboard')
            ->assertOk();

        $can = $response->inertiaProps('can');
        $this->assertFalse($can['plant_request.create']);
        $this->assertFalse($response->inertiaProps('nav.isApprover'));
    }
}
