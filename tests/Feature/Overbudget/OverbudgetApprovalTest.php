<?php

namespace Tests\Feature\Overbudget;

use App\Models\OverbudgetRequest;
use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Models\RequestApproval;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class OverbudgetApprovalTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_finance_and_ops_directors_approve_overbudget_and_unlock_plant_request(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, amount: '10000000.00');
        $planner = $this->makeUserWithRole('planner');
        $finDir = $this->makeFinanceDirector();
        $opsDir = User::factory()->create(['is_active' => true, 'project_code_scope' => null]);
        setPermissionsTeamId('');
        $opsDir->assignRole('operation_director');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'sap_mr_id' => 11001,
            'estimated_total' => '13000000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        $overbudget = OverbudgetRequest::create([
            'budget_allocation_id' => $allocation->id,
            'plant_request_id' => $plantRequest->id,
            'requested_amount' => '13000000.00',
            'over_pct' => '30.00',
            'justification' => 'Emergency engine repair',
            'requested_by' => $planner->id,
            'status' => 'pending_fin_dir',
        ]);
        app(\App\Services\Approval\ApprovalEngine::class)->initiate(
            $overbudget,
            \App\Support\ApprovalChains::for('OverbudgetRequest')
        );

        $finApproval = RequestApproval::query()
            ->where('approvable_id', $overbudget->id)
            ->where('required_role', 'finance_director')
            ->first();

        setPermissionsTeamId('');
        $this->actingAs($finDir)
            ->withoutVite()
            ->post("/approvals/{$finApproval->id}/decide", ['decision' => 'approved'])
            ->assertRedirect();

        $overbudget->refresh();
        $this->assertSame('pending_ops_dir', $overbudget->status);

        $opsApproval = RequestApproval::query()
            ->where('approvable_id', $overbudget->id)
            ->where('required_role', 'operation_director')
            ->first();

        setPermissionsTeamId('');
        $this->actingAs($opsDir)
            ->withoutVite()
            ->post("/approvals/{$opsApproval->id}/decide", ['decision' => 'approved'])
            ->assertRedirect();

        $overbudget->refresh();
        $plantRequest->refresh();

        $this->assertSame('approved', $overbudget->status);
        $this->assertSame('pending_pm', $plantRequest->status);

        $this->assertDatabaseHas('budget_ledgers', [
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'overbudget',
            'ref_type' => 'overbudget_request',
            'ref_id' => $overbudget->id,
        ]);
    }

    public function test_finance_director_rejection_marks_overbudget_rejected(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $finDir = $this->makeFinanceDirector();

        $overbudget = OverbudgetRequest::create([
            'budget_allocation_id' => $allocation->id,
            'requested_amount' => '15000000.00',
            'over_pct' => '50.00',
            'justification' => 'Not justified',
            'requested_by' => $planner->id,
            'status' => 'pending_fin_dir',
        ]);
        app(\App\Services\Approval\ApprovalEngine::class)->initiate(
            $overbudget,
            \App\Support\ApprovalChains::for('OverbudgetRequest')
        );

        $approval = RequestApproval::query()
            ->where('approvable_id', $overbudget->id)
            ->where('required_role', 'finance_director')
            ->first();

        setPermissionsTeamId('');
        $this->actingAs($finDir)
            ->withoutVite()
            ->post("/approvals/{$approval->id}/decide", [
                'decision' => 'rejected',
                'remarks' => 'Insufficient justification',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $overbudget->fresh()->status);
    }
}
