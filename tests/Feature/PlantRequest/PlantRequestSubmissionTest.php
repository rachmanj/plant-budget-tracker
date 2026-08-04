<?php

namespace Tests\Feature\PlantRequest;

use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PlantRequestSubmissionTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_submit_within_110_percent_tolerance_posts_commitment_and_starts_approval(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, amount: '10000000.00');
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'sap_mr_id' => 8001,
            'estimated_total' => '11000000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/submit")
            ->assertRedirect(route('plant-requests.show', $plantRequest));

        $plantRequest->refresh();
        $this->assertSame('pending_pm', $plantRequest->status);
        $this->assertNotNull($plantRequest->submitted_at);

        $this->assertDatabaseHas('budget_ledgers', [
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'commitment',
            'ref_type' => 'plant_request',
            'ref_id' => $plantRequest->id,
        ]);

        $this->assertDatabaseHas('request_approvals', [
            'approvable_type' => PlantRequest::class,
            'approvable_id' => $plantRequest->id,
            'step_order' => 1,
            'required_role' => 'project_manager',
            'decision' => 'pending',
        ]);
    }

    public function test_submit_over_110_percent_redirects_to_overbudget_flow(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, amount: '10000000.00');
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'sap_mr_id' => 8002,
            'estimated_total' => '12000000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        $response = $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/submit");

        $response->assertRedirect(route('overbudget.create', [
            'plant_request_id' => $plantRequest->id,
            'budget_allocation_id' => $allocation->id,
            'requested_amount' => '12000000.00',
            'over_pct' => '10.00',
        ]));

        $plantRequest->refresh();
        $this->assertSame('draft', $plantRequest->status);
        $this->assertDatabaseMissing('budget_ledgers', [
            'ref_type' => 'plant_request',
            'ref_id' => $plantRequest->id,
            'entry_type' => 'commitment',
        ]);
    }

    public function test_overbudget_store_creates_request_with_approval_chain(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'estimated_total' => '12000000.00',
            'requested_by' => $planner->id,
            'status' => 'draft',
            'sap_mr_id' => 8003,
        ]);

        $this->actingAsProject($planner)
            ->post('/overbudget', [
                'budget_allocation_id' => $allocation->id,
                'plant_request_id' => $plantRequest->id,
                'requested_amount' => '12000000.00',
                'over_pct' => '20.00',
                'justification' => 'Critical breakdown repair',
            ])
            ->assertRedirect(route('overbudget.index'));

        $this->assertDatabaseHas('overbudget_requests', [
            'plant_request_id' => $plantRequest->id,
            'status' => 'pending_fin_dir',
            'requested_by' => $planner->id,
        ]);

        $this->assertDatabaseHas('request_approvals', [
            'required_role' => 'finance_director',
            'decision' => 'pending',
        ]);
    }
}
