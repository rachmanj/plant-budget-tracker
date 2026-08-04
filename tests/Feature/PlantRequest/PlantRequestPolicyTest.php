<?php

namespace Tests\Feature\PlantRequest;

use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Models\User;
use App\Policies\PlantRequestPolicy;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PlantRequestPolicyTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_planner_and_mechanic_can_create_plant_requests(): void
    {
        $policy = new PlantRequestPolicy();

        $planner = $this->makeUserWithRole('planner');
        setPermissionsTeamId('MBL');
        $this->assertTrue($policy->create($planner));

        $mechanic = $this->makeUserWithRole('mechanic');
        setPermissionsTeamId('MBL');
        $this->assertTrue($policy->create($mechanic));
    }

    public function test_buyer_cannot_create_plant_requests(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        setPermissionsTeamId('MBL');

        $this->assertFalse((new PlantRequestPolicy())->create($buyer));
        $this->assertFalse(Gate::forUser($buyer)->allows('create', PlantRequest::class));
    }

    public function test_only_requester_can_update_draft(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $other = $this->makeUserWithRole('mechanic');

        $request = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);

        $policy = new PlantRequestPolicy();
        setPermissionsTeamId('MBL');
        $this->assertTrue($policy->update($planner, $request));

        setPermissionsTeamId('MBL');
        $this->assertFalse($policy->update($other, $request));

        $request->update(['status' => 'pending_pm']);
        setPermissionsTeamId('MBL');
        $this->assertFalse($policy->update($planner, $request->fresh()));
    }

    public function test_submit_requires_lines_and_sap_mr_link(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $withoutLines = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
            'sap_mr_id' => 0,
        ]);

        $policy = new PlantRequestPolicy();
        setPermissionsTeamId('MBL');
        $this->assertFalse($policy->submit($planner, $withoutLines));

        $withLines = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
            'sap_mr_id' => 1234,
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $withLines->id]);

        setPermissionsTeamId('MBL');
        $this->assertTrue($policy->submit($planner, $withLines));
    }

    public function test_cancellation_permissions_follow_role_matrix(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $buyer = $this->makeUserWithRole('buyer');

        $request = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
        ]);

        $policy = new PlantRequestPolicy();

        setPermissionsTeamId('MBL');
        $this->assertTrue($policy->cancel($planner, $request));

        setPermissionsTeamId('MBL');
        $this->assertTrue($policy->cancel($buyer, $request));
    }
}
