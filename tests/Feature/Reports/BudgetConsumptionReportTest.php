<?php

namespace Tests\Feature\Reports;

use App\Models\PlantRequest;
use App\Services\Reporting\BudgetConsumptionReport;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class BudgetConsumptionReportTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_by_project_returns_project_summary_from_allocation(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, project: 'MBL', amount: '10000000.00');

        $report = app(BudgetConsumptionReport::class);
        $data = $report->byProject('MBL', now()->startOfMonth());

        $this->assertNotNull($data['summary']);
        $this->assertSame($allocation->id, $data['summary']['allocation_id']);
        $this->assertSame('10000000.00', $data['summary']['pagu']);
        $this->assertSame('0.00', $data['summary']['committed']);
    }

    public function test_unit_breakdown_comes_from_plant_requests_not_allocations(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, project: 'MBL', amount: '10000000.00');
        $planner = $this->makeUserWithRole('planner');

        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'estimated_total' => '1500000.00',
            'requested_by' => $planner->id,
            'status' => 'approved',
            'sap_mr_id' => 1,
        ]);

        $latestOnUnit42 = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 42,
            'unit_code_cache' => 'E-042',
            'estimated_total' => '500000.00',
            'requested_by' => $planner->id,
            'status' => 'pending_pm',
            'sap_mr_id' => 2,
        ]);
        PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'equipment_id' => 99,
            'unit_code_cache' => 'E-099',
            'estimated_total' => '800000.00',
            'requested_by' => $planner->id,
            'status' => 'pr_created',
            'sap_mr_id' => 3,
        ]);

        $data = app(BudgetConsumptionReport::class)->byProject('MBL', now()->startOfMonth());

        $this->assertCount(2, $data['units']);
        $unit42 = collect($data['units'])->firstWhere('equipment_id', 42);
        $this->assertSame(2, $unit42['request_count']);
        $this->assertSame('2000000.00', $unit42['total_estimated']);
        $this->assertSame('pending_pm', $unit42['last_status']);
    }

    public function test_planner_can_view_budget_consumption_report_page(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/reports/budget-consumption?project_code=MBL')
            ->assertOk();
    }

    public function test_rolling_six_month_includes_six_entries(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $rolling = app(BudgetConsumptionReport::class)->rollingSixMonth('MBL');

        $this->assertCount(6, $rolling);
        $this->assertArrayHasKey('month', $rolling->first());
        $this->assertArrayHasKey('data', $rolling->first());
        $this->assertArrayHasKey('summary', $rolling->first()['data']);
    }
}
