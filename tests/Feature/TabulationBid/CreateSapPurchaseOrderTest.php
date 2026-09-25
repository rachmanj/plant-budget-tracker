<?php

namespace Tests\Feature\TabulationBid;

use App\Jobs\CreateSapPurchaseOrder;
use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Models\SapSyncLog;
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

class CreateSapPurchaseOrderTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_job_dispatch_is_queued(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $bid = $this->createAwardedBid($buyer);

        $this->actingAsProject($admin)
            ->post("/tabulation-bids/{$bid->id}/create-po")
            ->assertRedirect();

        Queue::assertPushed(CreateSapPurchaseOrder::class);
    }

    public function test_job_is_idempotent_when_po_already_exists(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $bid = TabulationBid::factory()->create([
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
            'sap_po_id' => '99999',
        ]);
        $vendor = TabulationBidVendor::factory()->create(['tabulation_bid_id' => $bid->id]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $bid->id,
            'tabulation_bid_vendor_id' => $vendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now(),
        ]);

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldNotReceive('createPurchaseOrder');

        $job = new CreateSapPurchaseOrder($bid->id);
        $job->handle($sapService, app(SapCircuitBreaker::class));

        $this->assertSame('99999', $bid->fresh()->sap_po_id);
    }

    public function test_job_creates_po_once_and_marks_sync_log_success(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $bid = $this->createAwardedBid($buyer);
        $this->createLinkedPlantRequestForBid($bid);

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldReceive('createPurchaseOrder')
            ->once()
            ->andReturn(['DocEntry' => 54321]);

        $breaker = app(SapCircuitBreaker::class);
        $job = new CreateSapPurchaseOrder($bid->id);

        $job->handle($sapService, $breaker);
        $job->handle($sapService, $breaker);

        $bid->refresh();
        $this->assertSame('54321', $bid->sap_po_id);
        $this->assertSame('po_created', $bid->status);
        $this->assertFalse($bid->sap_sync_failed);

        $log = SapSyncLog::query()
            ->where('correlation_key', "create_po:tabulation_bid:{$bid->id}")
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame(1, $log->attempts);
    }

    public function test_payload_uses_item_code_doc_due_date_and_awarded_vendor_card_code(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $bid = $this->createAwardedBid($buyer, 'PR-PAYLOAD-01');
        $this->createLinkedPlantRequestForBid($bid, 'PR-PAYLOAD-01', 'PART-PO-99');

        $captured = null;
        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldReceive('createPurchaseOrder')
            ->once()
            ->with(Mockery::on(function (array $payload) use (&$captured) {
                $captured = $payload;

                return true;
            }))
            ->andReturn(['DocEntry' => 11111]);

        $job = new CreateSapPurchaseOrder($bid->id);
        $job->handle($sapService, app(SapCircuitBreaker::class));

        $this->assertNotNull($captured);
        $this->assertSame('V-SAP', $captured['CardCode']);
        $this->assertArrayHasKey('DocDueDate', $captured);
        $this->assertArrayHasKey('Comments', $captured);
        $this->assertSame('PART-PO-99', $captured['DocumentLines'][0]['ItemCode']);
        $this->assertArrayNotHasKey('ItemDescription', $captured['DocumentLines'][0]);
    }

    public function test_job_fails_when_awarded_plant_request_missing_and_does_not_call_sap(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $bid = $this->createAwardedBid($buyer, 'PR-MISSING-77');

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldNotReceive('createPurchaseOrder');

        $job = new CreateSapPurchaseOrder($bid->id);

        try {
            $job->handle($sapService, app(SapCircuitBreaker::class));
            $this->fail('Expected job to throw when plant request is missing.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Awarded plant request not found', $e->getMessage());
            $this->assertStringContainsString('PR-MISSING-77', $e->getMessage());
        }

        $log = SapSyncLog::query()
            ->where('correlation_key', "create_po:tabulation_bid:{$bid->id}")
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('pending', $log->status);
        $this->assertStringContainsString('Awarded plant request not found', (string) $log->error_message);
    }

    private function createLinkedPlantRequestForBid(
        TabulationBid $bid,
        ?string $sapPrNo = null,
        string $partNumber = 'PN-LINKED',
    ): PlantRequest {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $sapPrNo ??= $bid->sap_pr_id;

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'approved',
            'sap_pr_no' => $sapPrNo,
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $plantRequest->id,
            'part_number' => $partNumber,
            'qty' => 2,
            'unit_price_est' => '500000.00',
        ]);

        return $plantRequest;
    }

    private function createAwardedBid(\App\Models\User $buyer, ?string $sapPrId = null): TabulationBid
    {
        $bid = TabulationBid::factory()->create([
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
            'sap_pr_id' => $sapPrId ?? (string) fake()->numberBetween(20000, 29999),
        ]);
        $vendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $bid->id,
            'vendor_code' => 'V-SAP',
            'price' => '2500000.00',
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
