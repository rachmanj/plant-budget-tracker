<?php

namespace Tests\Feature\TabulationBid;

use App\Jobs\CreateSapPurchaseOrder;
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

    private function createAwardedBid(\App\Models\User $buyer): TabulationBid
    {
        $bid = TabulationBid::factory()->create([
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
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
