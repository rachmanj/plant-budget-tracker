<?php

namespace Tests\Feature\PlantRequest;

use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Models\RequestApproval;
use App\Services\Approval\ApprovalEngine;
use App\Support\ApprovalChains;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ApprovalChainTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_pm_then_plant_manager_approval_chain_completes(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $pm = $this->makeUserWithRole('project_manager');
        $plantMgr = $this->makeUserWithRole('plant_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'sap_mr_id' => 9001,
            'estimated_total' => '5000000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/submit")
            ->assertRedirect();

        $pmApproval = RequestApproval::query()
            ->where('approvable_id', $plantRequest->id)
            ->where('step_order', 1)
            ->first();

        setPermissionsTeamId('MBL');
        $this->actingAs($pm)
            ->withoutVite()
            ->withSession(['current_project' => 'MBL'])
            ->post("/approvals/{$pmApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertRedirect();

        $plantRequest->refresh();
        $this->assertSame('pending_plant_mgr', $plantRequest->status);

        $plantApproval = RequestApproval::query()
            ->where('approvable_id', $plantRequest->id)
            ->where('step_order', 2)
            ->first();

        setPermissionsTeamId('MBL');
        $this->actingAs($plantMgr)
            ->withoutVite()
            ->withSession(['current_project' => 'MBL'])
            ->post("/approvals/{$plantApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertRedirect();

        $plantRequest->refresh();
        $this->assertSame('approved', $plantRequest->status);
    }

    public function test_pm_rejection_reverses_commitment_and_marks_rejected(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $pm = $this->makeUserWithRole('project_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'sap_mr_id' => 9002,
            'estimated_total' => '3000000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/submit");

        $this->assertDatabaseHas('budget_ledgers', [
            'entry_type' => 'commitment',
            'ref_type' => 'plant_request',
            'ref_id' => $plantRequest->id,
        ]);

        $pmApproval = RequestApproval::query()
            ->where('approvable_id', $plantRequest->id)
            ->where('step_order', 1)
            ->first();

        setPermissionsTeamId('MBL');
        $this->actingAs($pm)
            ->withoutVite()
            ->withSession(['current_project' => 'MBL'])
            ->post("/approvals/{$pmApproval->id}/decide", [
                'decision' => 'rejected',
                'remarks' => 'Insufficient detail',
            ])
            ->assertRedirect();

        $plantRequest->refresh();
        $this->assertSame('rejected', $plantRequest->status);

        $this->assertDatabaseHas('budget_ledgers', [
            'entry_type' => 'reversal',
            'ref_type' => 'plant_request',
            'ref_id' => $plantRequest->id,
        ]);
    }

    public function test_approval_engine_initiates_correct_chain_steps(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);

        $engine = app(ApprovalEngine::class);
        $engine->initiate($plantRequest, ApprovalChains::for('PlantRequest'));

        $plantRequest->refresh();
        $this->assertSame('pending_pm', $plantRequest->status);
        $this->assertCount(2, $plantRequest->approvals);

        $roles = $plantRequest->approvals()->orderBy('step_order')->pluck('required_role')->all();
        $this->assertSame(['project_manager', 'plant_manager'], $roles);
    }
}
