<?php

namespace Tests\Feature\Cancellation;

use App\Models\CancellationRequest;
use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Services\Cancellation\CancelRequestService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class CancellationStageGateTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_plant_cannot_cancel_when_po_has_been_sent(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'approved',
            'sap_mr_id' => 12001,
        ]);

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/cancel", [
                'po_stage' => 'sent',
                'reason' => 'Should fail',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('cancellation_requests', 0);
    }

    public function test_plant_can_cancel_when_po_not_sent(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'approved',
            'estimated_total' => '2000000.00',
            'sap_mr_id' => 12002,
        ]);

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/cancel", [
                'po_stage' => 'created',
                'reason' => 'Part no longer needed',
            ])
            ->assertRedirect(route('cancellation.index'));

        $this->assertDatabaseHas('cancellation_requests', [
            'plant_request_id' => $plantRequest->id,
            'initiated_by' => 'plant',
            'po_stage' => 'created',
            'status' => 'pending',
        ]);
    }

    public function test_procurement_must_agree_when_plant_initiates_cancellation(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $buyer = $this->makeUserWithRole('buyer');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'pending_pm',
            'estimated_total' => '1500000.00',
            'sap_mr_id' => 12003,
        ]);
        PlantRequestLine::factory()->create(['plant_request_id' => $plantRequest->id]);

        app(\App\Services\Budget\BudgetEngine::class)->postCommitment(
            $allocation,
            '1500000.00',
            'plant_request',
            $plantRequest->id,
            $planner,
            'Test commitment'
        );

        $cancellation = CancellationRequest::create([
            'plant_request_id' => $plantRequest->id,
            'po_stage' => 'approved',
            'initiated_by' => 'plant',
            'status' => 'pending',
            'budget_reversal_amount' => '1500000.00',
            'reason' => 'Duplicate request',
        ]);

        $this->actingAsProject($buyer)
            ->post("/cancellation-requests/{$cancellation->id}/agree")
            ->assertRedirect();

        $cancellation->refresh();
        $plantRequest->refresh();

        $this->assertSame('approved', $cancellation->status);
        $this->assertSame('cancelled', $plantRequest->status);
        $this->assertNotNull($cancellation->agreed_at);
    }

    public function test_cancel_service_blocks_plant_when_po_sent(): void
    {
        $service = app(CancelRequestService::class);
        $plantRequest = PlantRequest::factory()->make();

        $this->assertFalse($service->canPlantCancel($plantRequest, 'sent'));
        $this->assertTrue($service->canPlantCancel($plantRequest, 'created'));
        $this->assertTrue($service->canPlantCancel($plantRequest, null));
    }
}
