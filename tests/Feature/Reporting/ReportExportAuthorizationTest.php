<?php

namespace Tests\Feature\Reporting;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ReportExportAuthorizationTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_export_pdf_returns_200_for_user_with_reports_export(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $manager = $this->makeUserWithRole('plant_manager');

        $month = now()->format('Y-m');

        $this->actingAsProject($manager)
            ->get("/reports/budget-consumption/export/pdf?project_code=MBL&month={$month}")
            ->assertOk();
    }

    public function test_export_pdf_returns_403_without_reports_export(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $planner = $this->makeUserWithRole('planner');
        $month = now()->format('Y-m');

        $this->actingAsProject($planner)
            ->get("/reports/budget-consumption/export/pdf?project_code=MBL&month={$month}")
            ->assertForbidden();
    }

    public function test_export_csv_returns_200_for_user_with_reports_export(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $manager = $this->makeUserWithRole('plant_manager');
        $month = now()->format('Y-m');

        $this->actingAsProject($manager)
            ->get("/reports/budget-consumption/export/csv?project_code=MBL&month={$month}")
            ->assertOk();
    }

    public function test_export_csv_returns_403_without_reports_export(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $planner = $this->makeUserWithRole('planner');
        $month = now()->format('Y-m');

        $this->actingAsProject($planner)
            ->get("/reports/budget-consumption/export/csv?project_code=MBL&month={$month}")
            ->assertForbidden();
    }

    public function test_budget_consumption_page_exposes_can_export_true_for_exporter(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $manager = $this->makeUserWithRole('plant_manager');

        $this->actingAsProject($manager)
            ->get('/reports/budget-consumption?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/BudgetConsumption', false)
                ->where('can.export', true));
    }

    public function test_budget_consumption_page_exposes_can_export_false_without_export_permission(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/reports/budget-consumption?project_code=MBL')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/BudgetConsumption', false)
                ->where('can.export', false));
    }

    public function test_reports_index_renders_report_list(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index', false)
                ->has('reports', 3));
    }
}
