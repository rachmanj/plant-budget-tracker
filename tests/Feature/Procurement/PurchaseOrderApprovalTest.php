<?php

namespace Tests\Feature\Procurement;

use App\Jobs\CreateSapPurchaseOrder;
use App\Models\RequestApproval;
use App\Models\TabulationBid;
use App\Models\TabulationBidVendor;
use App\Services\Sap\SapCircuitBreaker;
use App\Services\Sap\SapService;
use App\Support\ApprovalChains;
use App\Support\ProcurementSettings;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PurchaseOrderApprovalTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_low_value_bid_requires_only_procurement_manager_without_director_step(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '50000000.00', '60000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ])
            ->assertRedirect();

        $bid->refresh();
        $this->assertSame('forwarded_admin', $bid->status);
        $this->assertFalse($bid->approvals()->where('step_order', 2)->exists());
        $this->assertCount(1, $bid->approvals);
    }

    public function test_high_value_bid_after_award_adds_president_director_step_and_pending_presdir_status(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '150000000.00', '160000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ])
            ->assertRedirect();

        $bid->refresh();
        $this->assertSame('pending_presdir', $bid->status);
        $this->assertDatabaseHas('request_approvals', [
            'approvable_type' => TabulationBid::class,
            'approvable_id' => $bid->id,
            'step_order' => 2,
            'required_role' => 'president_director',
            'decision' => 'pending',
        ]);
    }

    public function test_create_po_rejected_while_president_director_approval_pending(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '200000000.00', '210000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $this->actingAsProject($admin)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'approval is still in progress')
                && str_contains($message, 'president_director'));

        Queue::assertNothingPushed();
    }

    public function test_create_po_dispatches_job_after_president_director_approves(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $procMgr = $this->makeUserWithRole('procurement_manager');
        $presDir = $this->makeUserWithRole('president_director');

        $bid = $this->createSubmittedBid($buyer, '200000000.00', '210000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $presApproval = RequestApproval::query()
            ->where('approvable_id', $bid->id)
            ->where('step_order', 2)
            ->first();

        $this->actingAsProject($presDir)
            ->post("/approvals/{$presApproval->id}/decide", ['decision' => 'approved'])
            ->assertRedirect();

        $bid->refresh();
        $this->assertSame('forwarded_admin', $bid->status);

        $this->actingAsProject($admin)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertRedirect();

        Queue::assertPushed(CreateSapPurchaseOrder::class);
    }

    public function test_rejection_at_step_one_blocks_create_po(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '50000000.00', '60000000.00');

        $stepOne = $bid->approvals()->where('step_order', 1)->first();
        $this->actingAsProject($procMgr)
            ->post("/approvals/{$stepOne->id}/decide", ['decision' => 'rejected'])
            ->assertRedirect();

        $this->actingAsProject($admin)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertRedirect()
            ->assertSessionHas('error', 'A winning vendor must be awarded before creating a purchase order.');

        Queue::assertNothingPushed();
    }

    public function test_procurement_admin_create_po_without_award_returns_error_not_403(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '50000000.00', '60000000.00', 'PR-NO-AWARD-PO');
        $this->approveProcurementManager($bid, $procMgr);

        $this->actingAsProject($admin)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertRedirect()
            ->assertSessionHas('error', 'A winning vendor must be awarded before creating a purchase order.');

        Queue::assertNothingPushed();
    }

    public function test_non_procurement_admin_create_po_returns_403(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '50000000.00', '60000000.00', 'PR-403-ROLE');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_bid_creator_cannot_create_po_even_with_procurement_admin_role(): void
    {
        Queue::fake();

        $buyerAdmin = $this->makeUserWithRole('buyer');
        setPermissionsTeamId('MBL');
        $buyerAdmin->assignRole('procurement_admin');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyerAdmin, '50000000.00', '60000000.00', 'PR-SOD-PO');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $this->actingAsProject($buyerAdmin)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_bid_review_enables_create_po_and_dispatch_after_full_approval(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $procMgr = $this->makeUserWithRole('procurement_manager');
        $presDir = $this->makeUserWithRole('president_director');

        $bid = $this->createSubmittedBid($buyer, '200000000.00', '210000000.00', 'PR-ENABLE-PO');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $presApproval = RequestApproval::query()
            ->where('approvable_id', $bid->id)
            ->where('step_order', 2)
            ->firstOrFail();

        $this->actingAsProject($presDir)
            ->post("/approvals/{$presApproval->id}/decide", ['decision' => 'approved'])
            ->assertRedirect();

        $this->actingAsProject($admin)
            ->withoutVite()
            ->get(route('tabulation-bids.show', $bid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('poCreate.enabled', true)
                ->where('can.createPo', true)
            );

        $this->actingAsProject($admin)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertRedirect();

        Queue::assertPushed(CreateSapPurchaseOrder::class);
    }

    public function test_changing_threshold_via_procurement_settings_flips_director_requirement(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        ProcurementSettings::setPoDirectorThreshold('50000000', $procMgr);

        $bid = $this->createSubmittedBid($buyer, '40000000.00', '45000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $bid->refresh();
        $this->assertSame('forwarded_admin', $bid->status);
        $this->assertFalse($bid->approvals()->where('step_order', 2)->exists());

        ProcurementSettings::setPoDirectorThreshold('30000000', $procMgr);

        $bidHigh = $this->createSubmittedBid($buyer, '40000000.00', '45000000.00', 'PR-THRESH-2');
        $this->approveProcurementManager($bidHigh, $procMgr);
        $vendorHigh = $bidHigh->vendors()->orderBy('price')->first();

        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bidHigh), [
                'tabulation_bid_vendor_id' => $vendorHigh->id,
            ]);

        $bidHigh->refresh();
        $this->assertSame('pending_presdir', $bidHigh->status);
    }

    public function test_value_exactly_at_threshold_does_not_require_director_step(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '100000000.00', '110000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $bid->refresh();
        $this->assertSame('forwarded_admin', $bid->status);
        $this->assertFalse($bid->approvals()->where('step_order', 2)->exists());
    }

    public function test_director_step_not_duplicated_when_award_logic_runs_twice(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '150000000.00', '160000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();

        $this->runHighValueAwardSideEffects($bid, $vendor, $procMgr);
        $this->runHighValueAwardSideEffects($bid, $vendor, $procMgr);

        $this->assertSame(
            1,
            $bid->approvals()->where('step_order', 2)->count()
        );
    }

    public function test_procurement_manager_can_view_and_update_po_threshold(): void
    {
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $this->actingAsProject($procMgr)
            ->withoutVite()
            ->get(route('procurement.settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Procurement/Settings', false));

        $this->actingAsProject($procMgr)
            ->post(route('procurement.settings.update'), [
                'po_director_threshold_idr' => 75000000,
            ])
            ->assertRedirect();

        $this->assertSame(75000000.0, ProcurementSettings::poDirectorThreshold());

        $this->actingAsProject($procMgr)
            ->withoutVite()
            ->get(route('procurement.settings.index'))
            ->assertInertia(fn ($page) => $page->where('threshold', '75000000.00'));
    }

    public function test_mechanic_cannot_access_or_save_procurement_settings(): void
    {
        $mechanic = $this->makeUserWithRole('mechanic');

        $this->actingAsProject($mechanic)
            ->get(route('procurement.settings.index'))
            ->assertForbidden();

        $this->actingAsProject($mechanic)
            ->post(route('procurement.settings.update'), [
                'po_director_threshold_idr' => 50000000,
            ])
            ->assertForbidden();
    }

    public function test_negative_po_threshold_rejected_with_422(): void
    {
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $this->actingAsProject($procMgr)
            ->post(route('procurement.settings.update'), [
                'po_director_threshold_idr' => -1,
            ])
            ->assertSessionHasErrors('po_director_threshold_idr');
    }

    public function test_approvals_index_includes_bid_document_number_and_amount(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $presDir = $this->makeUserWithRole('president_director');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '200000000.00', '210000000.00', 'PR-APPROVAL-UI');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $this->actingAsProject($presDir)
            ->withoutVite()
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('approvals.data', 1)
                ->where('approvals.data.0.document.document_no', $bid->fresh()->bid_no)
                ->where('approvals.data.0.document.amount', '200000000.00')
            );
    }

    public function test_reject_without_remarks_returns_422_but_approve_without_remarks_succeeds(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '50000000.00', '60000000.00', 'PR-REMARKS');
        $approval = $bid->approvals()->where('step_order', 1)->firstOrFail();

        $this->actingAsProject($procMgr)
            ->post("/approvals/{$approval->id}/decide", ['decision' => 'rejected'])
            ->assertSessionHasErrors('remarks');

        $this->actingAsProject($procMgr)
            ->post("/approvals/{$approval->id}/decide", ['decision' => 'approved'])
            ->assertRedirect();
    }

    public function test_create_po_without_permission_returns_403_even_when_bid_has_no_award(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $mechanic = $this->makeUserWithRole('mechanic');

        $bid = $this->createSubmittedBid($buyer, '50000000.00', '60000000.00', 'PR-403-PO');

        $this->actingAsProject($mechanic)
            ->post(route('tabulation-bids.create-po', $bid))
            ->assertForbidden();
    }

    public function test_bid_review_disables_create_po_while_approval_pending(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '200000000.00', '210000000.00', 'PR-DISABLE-PO');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $this->actingAsProject($admin)
            ->withoutVite()
            ->get(route('tabulation-bids.show', $bid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('poCreate.visible', true)
                ->where('poCreate.enabled', false)
            );
    }

    public function test_create_sap_purchase_order_job_exits_without_calling_sap_when_not_fully_approved(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $procMgr = $this->makeUserWithRole('procurement_manager');

        $bid = $this->createSubmittedBid($buyer, '200000000.00', '210000000.00');
        $this->approveProcurementManager($bid, $procMgr);

        $vendor = $bid->vendors()->orderBy('price')->first();
        $this->actingAsProject($procMgr)
            ->post(route('tabulation-bids.award', $bid), [
                'tabulation_bid_vendor_id' => $vendor->id,
            ]);

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldNotReceive('createPurchaseOrder');

        $job = new CreateSapPurchaseOrder($bid->id);
        $job->handle($sapService, app(SapCircuitBreaker::class), app(\App\Services\Approval\ApprovalEngine::class));

        $this->assertNull($bid->fresh()->sap_po_id);
    }

    private function createSubmittedBid(
        \App\Models\User $buyer,
        string $lowPrice,
        string $highPrice,
        string $sapPrId = 'PR-PO-APP',
    ): TabulationBid {
        $this->actingAsProject($buyer)
            ->post('/tabulation-bids', [
                'sap_pr_id' => $sapPrId,
                'vendors' => [
                    [
                        'vendor_code' => 'V-LOW',
                        'vendor_name' => 'Low Vendor',
                        'price' => $lowPrice,
                        'stock_availability' => 'ready',
                    ],
                    [
                        'vendor_code' => 'V-HIGH',
                        'vendor_name' => 'High Vendor',
                        'price' => $highPrice,
                        'stock_availability' => 'ready',
                    ],
                ],
            ])
            ->assertRedirect();

        return TabulationBid::query()->where('sap_pr_id', $sapPrId)->firstOrFail();
    }

    private function approveProcurementManager(TabulationBid $bid, \App\Models\User $procMgr): void
    {
        $approval = $bid->approvals()->where('step_order', 1)->firstOrFail();

        $this->actingAsProject($procMgr)
            ->post("/approvals/{$approval->id}/decide", ['decision' => 'approved'])
            ->assertRedirect();
    }

    private function runHighValueAwardSideEffects(
        TabulationBid $bid,
        TabulationBidVendor $vendor,
        \App\Models\User $actor,
    ): void {
        $poChain = ApprovalChains::tabulationBidPoChainForValue($vendor->price);
        if (count($poChain) <= 1) {
            return;
        }

        if (! $bid->approvals()->where('step_order', 2)->exists()) {
            $bid->approvals()->create([
                'step_order' => 2,
                'required_role' => 'president_director',
                'decision' => 'pending',
            ]);
        }

        $bid->update([
            'status' => 'pending_presdir',
            'reviewed_by' => $actor->id,
        ]);
    }
}
