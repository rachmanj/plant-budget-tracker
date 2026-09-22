<?php

namespace Tests\Feature;

use App\Models\CancellationRequest;
use App\Models\InterchangeMap;
use App\Models\PlantRequest;
use App\Models\TabulationBid;
use App\Models\TabulationBidAward;
use App\Models\TabulationBidVendor;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class UiGapsTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_overbudget_create_renders_form_with_prefill(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->withoutVite()
            ->get('/overbudget/create?plant_request_id=5&budget_allocation_id=9&requested_amount=12000000&over_pct=15.5')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Overbudget/Index', false)
                ->where('showForm', true)
                ->where('prefill.plant_request_id', '5')
                ->where('prefill.budget_allocation_id', '9')
                ->where('prefill.requested_amount', '12000000')
                ->where('prefill.over_pct', '15.5')
            );
    }

    public function test_tabulation_bid_store_accepts_full_vendor_payload_from_ui_form(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $response = $this->actingAsProject($buyer)
            ->post('/tabulation-bids', [
                'sap_pr_id' => 'PR-UI-9001',
                'vendors' => [
                    [
                        'vendor_code' => 'V001',
                        'vendor_name' => 'Vendor Alpha',
                        'price' => 5000000,
                        'stock_availability' => 'ready',
                        'payment_terms' => 'Net 30',
                        'remarks' => 'Termin 1',
                    ],
                    [
                        'vendor_code' => 'V002',
                        'vendor_name' => 'Vendor Beta',
                        'price' => 4800000,
                        'stock_availability' => 'indent',
                        'payment_terms' => '',
                        'remarks' => '',
                    ],
                ],
            ]);

        $bid = TabulationBid::query()->where('sap_pr_id', 'PR-UI-9001')->first();
        $response->assertRedirect(route('tabulation-bids.show', $bid));

        $this->assertCount(2, $bid->vendors);
        $this->assertSame(1, $bid->vendors()->where('vendor_code', 'V002')->value('rank'));
        $this->assertSame('indent', $bid->vendors()->where('vendor_code', 'V002')->value('stock_availability'));
    }

    public function test_tabulation_bid_show_passes_create_po_ability_for_procurement_admin_not_buyer(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $admin = $this->makeUserWithRole('procurement_admin');

        $bid = TabulationBid::factory()->create([
            'status' => 'forwarded_admin',
            'created_by' => $buyer->id,
        ]);
        $vendor = TabulationBidVendor::factory()->create([
            'tabulation_bid_id' => $bid->id,
            'price' => '1500000.00',
            'rank' => 1,
        ]);
        TabulationBidAward::create([
            'tabulation_bid_id' => $bid->id,
            'tabulation_bid_vendor_id' => $vendor->id,
            'awarded_by' => $buyer->id,
            'awarded_at' => now(),
        ]);

        $this->actingAsProject($admin)
            ->withoutVite()
            ->get(route('tabulation-bids.show', $bid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TabulationBid/Review', false)
                ->where('can.createPo', true)
            );

        $this->actingAsProject($buyer)
            ->withoutVite()
            ->get(route('tabulation-bids.show', $bid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.createPo', false)
            );
    }

    public function test_plant_request_cancel_works_for_planner_and_rejects_sent_po_stage(): void
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

        $this->actingAsProject($planner)
            ->post("/plant-requests/{$plantRequest->id}/cancel", [
                'po_stage' => 'sent',
                'reason' => 'Should fail',
            ])
            ->assertStatus(422);
    }

    public function test_cancellation_agree_allowed_for_counterparty_role_only(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $buyer = $this->makeUserWithRole('buyer');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'pending_pm',
            'sap_mr_id' => 12003,
        ]);

        $cancellation = CancellationRequest::create([
            'plant_request_id' => $plantRequest->id,
            'po_stage' => 'approved',
            'initiated_by' => 'plant',
            'status' => 'pending',
            'budget_reversal_amount' => '1500000.00',
            'reason' => 'Duplicate request',
        ]);

        $this->actingAsProject($planner)
            ->post("/cancellation-requests/{$cancellation->id}/agree")
            ->assertForbidden();

        $this->actingAsProject($buyer)
            ->post("/cancellation-requests/{$cancellation->id}/agree")
            ->assertRedirect();
    }

    public function test_interchange_signoff_rejected_for_mapping_creator(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $map = InterchangeMap::factory()->create([
            'created_by' => $buyer->id,
            'genuine_part_number' => 'GEN-CREATOR',
            'oem_part_number' => 'OEM-CREATOR',
        ]);

        $this->actingAsProject($buyer)
            ->post("/interchange/{$map->id}/signoff")
            ->assertForbidden();
    }
}
