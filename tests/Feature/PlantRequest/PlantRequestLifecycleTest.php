<?php

namespace Tests\Feature\PlantRequest;

use App\Jobs\CreateSapPurchaseOrder;
use App\Jobs\CreateSapPurchaseRequest;
use App\Models\BudgetLedger;
use App\Models\PlantRequest;
use App\Models\TabulationBid;
use App\Models\TabulationBidAward;
use App\Models\TabulationBidVendor;
use App\Services\Sap\SapCircuitBreaker;
use App\Services\Sap\SapService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PlantRequestLifecycleTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_create_po_job_advances_plant_request_to_po_created(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $buyer = $this->makeUserWithRole('buyer');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'approved',
            'sap_pr_no' => 'PR-9001',
        ]);

        $bid = $this->createAwardedBid($buyer, 'PR-9001');

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldReceive('createPurchaseOrder')
            ->once()
            ->andReturn(['DocEntry' => 77001]);

        $job = new CreateSapPurchaseOrder($bid->id);
        $job->handle($sapService, app(SapCircuitBreaker::class));

        $plantRequest->refresh();
        $this->assertSame('po_created', $plantRequest->status);
        $this->assertSame('77001', $plantRequest->sap_po_id);
    }

    public function test_create_po_job_never_downgrades_a_received_plant_request(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $buyer = $this->makeUserWithRole('buyer');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'received',
            'sap_pr_no' => 'PR-9002',
            'sap_grpo_no' => 'GRPO-1',
            'received_at' => now(),
        ]);

        $bid = $this->createAwardedBid($buyer, 'PR-9002');

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldReceive('createPurchaseOrder')
            ->once()
            ->andReturn(['DocEntry' => 77002]);

        $job = new CreateSapPurchaseOrder($bid->id);
        $job->handle($sapService, app(SapCircuitBreaker::class));

        $plantRequest->refresh();
        $this->assertSame('received', $plantRequest->status);
    }

    public function test_create_po_job_does_nothing_when_no_matching_plant_request(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $bid = $this->createAwardedBid($buyer, 'PR-UNMATCHED');

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldReceive('createPurchaseOrder')
            ->once()
            ->andReturn(['DocEntry' => 77003]);

        $job = new CreateSapPurchaseOrder($bid->id);
        $job->handle($sapService, app(SapCircuitBreaker::class));

        $bid->refresh();
        $this->assertSame('po_created', $bid->status);
    }

    public function test_plant_manager_can_receive_plant_request_at_po_created_status(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $plantManager = $this->makeUserWithRole('plant_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'po_created',
            'sap_pr_no' => 'PR-1001',
            'sap_po_id' => 'PO-1001',
        ]);

        $ledgerCountBefore = BudgetLedger::query()->count();

        $response = $this->actingAsProject($plantManager)
            ->post("/plant-requests/{$plantRequest->id}/receive", [
                'sap_grpo_no' => 'GRPO-1001',
                'received_at' => now()->toDateString(),
                'note' => 'Barang lengkap sesuai PO.',
            ]);

        $response->assertRedirect(route('plant-requests.show', $plantRequest));

        $plantRequest->refresh();
        $this->assertSame('received', $plantRequest->status);
        $this->assertSame('GRPO-1001', $plantRequest->sap_grpo_no);
        $this->assertNotNull($plantRequest->received_at);
        $this->assertSame($plantManager->id, $plantRequest->received_by);

        $this->assertDatabaseHas('request_comments', [
            'plant_request_id' => $plantRequest->id,
            'author_id' => $plantManager->id,
        ]);

        $this->assertSame($ledgerCountBefore, BudgetLedger::query()->count());
    }

    public function test_receive_is_rejected_for_role_without_permission(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $buyer = $this->makeUserWithRole('buyer');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'po_created',
            'sap_pr_no' => 'PR-1002',
        ]);

        $this->actingAsProject($buyer)
            ->post("/plant-requests/{$plantRequest->id}/receive", [
                'sap_grpo_no' => 'GRPO-1002',
                'received_at' => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_receive_is_rejected_when_status_is_still_draft(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $plantManager = $this->makeUserWithRole('plant_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'draft',
        ]);

        $this->actingAsProject($plantManager)
            ->post("/plant-requests/{$plantRequest->id}/receive", [
                'sap_grpo_no' => 'GRPO-1003',
                'received_at' => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_receive_is_rejected_when_already_received(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $plantManager = $this->makeUserWithRole('plant_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'received',
            'sap_grpo_no' => 'GRPO-OLD',
            'received_at' => now()->subDay(),
        ]);

        $this->actingAsProject($plantManager)
            ->post("/plant-requests/{$plantRequest->id}/receive", [
                'sap_grpo_no' => 'GRPO-1004',
                'received_at' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_receive_validation_rejects_missing_grpo_number(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $plantManager = $this->makeUserWithRole('plant_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'po_created',
        ]);

        $response = $this->actingAsProject($plantManager)
            ->postJson("/plant-requests/{$plantRequest->id}/receive", [
                'sap_grpo_no' => '',
                'received_at' => now()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sap_grpo_no']);
        $this->assertStringContainsString('GRPO number is required.', $response->json('errors.sap_grpo_no.0'));
    }

    public function test_receive_validation_rejects_future_received_at(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $plantManager = $this->makeUserWithRole('plant_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'po_created',
        ]);

        $this->actingAsProject($plantManager)
            ->postJson("/plant-requests/{$plantRequest->id}/receive", [
                'sap_grpo_no' => 'GRPO-2001',
                'received_at' => now()->addDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['received_at']);
    }

    public function test_create_pr_can_only_be_triggered_by_procurement_admin_or_it_manager(): void
    {
        Queue::fake();

        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $admin = $this->makeUserWithRole('procurement_admin');
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'approved',
        ]);

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/create-pr")
            ->assertStatus(403);

        Queue::assertNotPushed(CreateSapPurchaseRequest::class);

        $this->actingAsProject($admin)
            ->post("/plant-requests/{$plantRequest->id}/create-pr")
            ->assertRedirect(route('plant-requests.show', $plantRequest));

        Queue::assertPushed(CreateSapPurchaseRequest::class, function ($job) use ($plantRequest) {
            return $job->plantRequestId === $plantRequest->id;
        });
    }

    public function test_create_pr_allows_it_manager(): void
    {
        Queue::fake();

        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $itManager = $this->makeUserWithRole('it_manager');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'approved',
        ]);

        $this->actingAsProject($itManager)
            ->post("/plant-requests/{$plantRequest->id}/create-pr")
            ->assertRedirect(route('plant-requests.show', $plantRequest));

        Queue::assertPushed(CreateSapPurchaseRequest::class);
    }

    private function createAwardedBid(\App\Models\User $buyer, string $sapPrId): TabulationBid
    {
        $bid = TabulationBid::factory()->create([
            'sap_pr_id' => $sapPrId,
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
        ]);
        $vendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $bid->id,
            'vendor_code' => 'V-LC',
            'price' => '1500000.00',
            'rank' => 1,
        ]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $bid->id,
            'tabulation_bid_vendor_id' => $vendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now(),
        ]);

        return $bid;
    }
}
