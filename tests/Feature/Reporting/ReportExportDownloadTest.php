<?php

namespace Tests\Feature\Reporting;

use App\Models\TabulationBidVendor;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ReportExportDownloadTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    private string $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->month = now()->format('Y-m');

        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        TabulationBidVendor::factory()->create([
            'vendor_code' => 'V-TEST',
            'vendor_name' => 'Vendor Uji',
            'stock_availability' => 'indent',
        ]);
    }

    #[DataProvider('reportTypesWithProjectMonth')]
    public function test_export_pdf_returns_non_empty_body_for_authorized_user(string $reportType, string $query): void
    {
        $manager = $this->makeUserWithRole('plant_manager');

        $response = $this->actingAsProject($manager)
            ->get("/reports/{$reportType}/export/pdf{$query}");

        $response->assertOk();
        $this->assertGreaterThan(0, strlen($response->getContent()));
    }

    #[DataProvider('reportTypesWithProjectMonth')]
    public function test_export_csv_returns_non_empty_body_for_authorized_user(string $reportType, string $query): void
    {
        $manager = $this->makeUserWithRole('plant_manager');

        $response = $this->actingAsProject($manager)
            ->get("/reports/{$reportType}/export/csv{$query}");

        $response->assertOk();
        $this->assertGreaterThan(0, strlen($response->streamedContent()));
    }

    #[DataProvider('reportTypesWithProjectMonth')]
    public function test_export_pdf_returns_403_without_reports_export(string $reportType, string $query): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get("/reports/{$reportType}/export/pdf{$query}")
            ->assertForbidden();
    }

    #[DataProvider('reportTypesWithProjectMonth')]
    public function test_export_csv_returns_403_without_reports_export(string $reportType, string $query): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get("/reports/{$reportType}/export/csv{$query}")
            ->assertForbidden();
    }

    public function test_unknown_report_type_pdf_returns_404_with_indonesian_message(): void
    {
        $manager = $this->makeUserWithRole('plant_manager');

        $response = $this->actingAsProject($manager)
            ->get('/reports/jenis-tidak-valid/export/pdf');

        $response->assertNotFound();
        $this->assertStringContainsString('Report type not found.', $response->getContent());
    }

    public function test_unknown_report_type_csv_returns_404_with_indonesian_message(): void
    {
        $manager = $this->makeUserWithRole('plant_manager');

        $response = $this->actingAsProject($manager)
            ->get('/reports/jenis-tidak-valid/export/csv');

        $response->assertNotFound();
        $this->assertStringContainsString('Report type not found.', $response->getContent());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function reportTypesWithProjectMonth(): array
    {
        $month = now()->format('Y-m');
        $scoped = "?project_code=MBL&month={$month}";

        return [
            'budget-consumption' => ['budget-consumption', $scoped],
            'vendor-performance' => ['vendor-performance', ''],
            'equipment-cost' => ['equipment-cost', $scoped],
        ];
    }
}
