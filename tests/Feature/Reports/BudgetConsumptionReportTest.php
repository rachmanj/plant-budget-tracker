<?php

namespace Tests\Feature\Reports;

use App\Models\BudgetLedger;
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

    public function test_by_project_returns_allocation_summaries_from_ledger(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, project: 'MBL', amount: '10000000.00');

        BudgetLedger::create([
            'budget_allocation_id' => $allocation->id,
            'entry_type' => 'commitment',
            'amount' => '-2500000.00',
            'posted_by' => $finance->id,
            'posted_at' => now(),
        ]);

        $report = app(BudgetConsumptionReport::class);
        $data = $report->byProject('MBL', now()->startOfMonth());

        $this->assertCount(1, $data);
        $this->assertSame($allocation->id, $data[0]['allocation_id']);
        $this->assertSame('10000000.00', $data[0]['allocated']);
        $this->assertSame('-2500000.00', $data[0]['committed']);
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
    }

    public function test_by_plant_type_filters_allocations(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance, amount: '10000000.00');

        $report = app(BudgetConsumptionReport::class);
        $month = now()->startOfMonth();

        $this->assertCount(0, $report->byPlantType('MBL', 'DIGGER', $month));

        $allocation->update(['plant_type_cache' => 'DIGGER']);

        $diggers = $report->byPlantType('MBL', 'DIGGER', $month);

        $this->assertCount(1, $diggers);
        $this->assertSame($allocation->id, $diggers[0]['allocation_id']);
    }
}
