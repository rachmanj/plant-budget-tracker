<?php

namespace Tests\Feature\Reporting;

use App\Models\ProjectCache;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseRequest;
use App\Models\SapPurchaseRequestLine;
use App\Services\Reporting\ApprovalTurnaroundReport;
use App\Services\Reporting\PurchaseOrderTrendReport;
use App\Services\Reporting\PurchaseRequestByDepartmentReport;
use App\Services\Reporting\PurchaseRequestStatusReport;
use App\Services\Reporting\TopSupplierReport;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ProcurementReportsTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config([
            'procurement.pr_department_codes' => ['4', '5'],
            'procurement.po_department_codes' => ['40', '200'],
        ]);

        ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'Maruwai',
            'is_active' => true,
        ]);
        ProjectCache::create([
            'project_code' => '022C',
            'project_name' => 'Project 022C',
            'is_active' => true,
        ]);
    }

    #[DataProvider('procurementReportRoutes')]
    public function test_authorized_role_can_open_procurement_report(string $path): void
    {
        $manager = $this->makeUserWithRole('procurement_manager');

        $this->actingAsProject($manager)
            ->get($path.'?from=2026-06-01&to=2026-06-30')
            ->assertOk();
    }

    #[DataProvider('procurementReportRoutes')]
    public function test_mechanic_is_forbidden_on_procurement_reports(string $path): void
    {
        $mechanic = $this->makeUserWithRole('mechanic');

        $this->actingAsProject($mechanic)
            ->get($path)
            ->assertForbidden();
    }

    public function test_date_range_filter_limits_purchase_request_status_rows(): void
    {
        $this->createPurchaseRequest(['doc_date' => '2026-06-10', 'pr_status' => 'O', 'total_amount' => '100.00']);
        $this->createPurchaseRequest(['doc_date' => '2026-05-01', 'pr_status' => 'O', 'total_amount' => '900.00']);

        $report = app(PurchaseRequestStatusReport::class);
        $data = $report->data(['from' => '2026-06-01', 'to' => '2026-06-30', 'project_code' => null, 'dept_code' => null]);

        $this->assertSame(1, $data['summary']['document_count']);
        $this->assertSame('100.00', $data['summary']['total_amount']);
    }

    public function test_purchase_request_status_groups_status_with_count_and_value(): void
    {
        $this->createPurchaseRequest([
            'doc_date' => '2026-06-05',
            'department_code' => '4',
            'pr_status' => 'O',
            'closed_status' => null,
            'total_amount' => '1000.00',
        ]);
        $this->createPurchaseRequest([
            'doc_date' => '2026-06-06',
            'department_code' => '4',
            'pr_status' => 'C',
            'closed_status' => 'Y',
            'total_amount' => '500.00',
        ]);

        $data = app(PurchaseRequestStatusReport::class)->data([
            'from' => '2026-06-01',
            'to' => '2026-06-30',
            'project_code' => null,
            'dept_code' => null,
        ]);

        $this->assertCount(2, $data['rows']);
        $open = collect($data['rows'])->firstWhere('pr_status', 'O');
        $this->assertNotNull($open);
        $this->assertSame(1, $open['document_count']);
        $this->assertSame('1000.00', $open['total_amount']);
    }

    public function test_purchase_order_trend_aggregates_monthly_and_splits_pmb_sap(): void
    {
        $this->createPurchaseOrder([
            'doc_date' => '2026-06-10',
            'origin' => 'pmb',
            'total_amount' => '2000.00',
        ]);
        $this->createPurchaseOrder([
            'doc_date' => '2026-06-20',
            'origin' => 'sap',
            'total_amount' => '3000.00',
        ]);
        $this->createPurchaseOrder([
            'doc_date' => '2026-07-01',
            'origin' => 'sap',
            'total_amount' => '1000.00',
        ]);

        $data = app(PurchaseOrderTrendReport::class)->data([
            'from' => '2026-06-01',
            'to' => '2026-06-30',
            'project_code' => null,
            'dept_code' => null,
        ]);

        $this->assertSame('month', $data['granularity']);
        $this->assertCount(1, $data['rows']);
        $row = $data['rows'][0];
        $this->assertSame(2, $row['document_count']);
        $this->assertSame('5000.00', $row['total_amount']);
        $this->assertSame(1, $row['pmb_count']);
        $this->assertSame(1, $row['sap_count']);
        $this->assertSame('2000.00', $row['pmb_amount']);
        $this->assertSame('3000.00', $row['sap_amount']);
    }

    public function test_top_supplier_sorts_by_value_and_share_is_correct(): void
    {
        $this->createPurchaseOrder([
            'doc_date' => '2026-06-01',
            'vendor_code' => 'V-B',
            'vendor_name' => 'Beta',
            'total_amount' => '1000.00',
        ]);
        $this->createPurchaseOrder([
            'doc_date' => '2026-06-02',
            'vendor_code' => 'V-A',
            'vendor_name' => 'Alpha',
            'total_amount' => '3000.00',
        ]);

        $data = app(TopSupplierReport::class)->data([
            'from' => '2026-06-01',
            'to' => '2026-06-30',
            'project_code' => null,
            'dept_code' => null,
        ]);

        $this->assertSame('V-A', $data['rows'][0]['vendor_code']);
        $this->assertSame('75.00', $data['rows'][0]['share_pct']);
        $this->assertSame('25.00', $data['rows'][1]['share_pct']);
    }

    public function test_approval_turnaround_computes_average_median_and_day_diff(): void
    {
        $this->createPurchaseRequest([
            'doc_num' => 50101,
            'doc_date' => '2026-06-01',
            'department_code' => '4',
            'department_name' => 'Plant',
        ]);
        $this->createPurchaseRequest([
            'doc_num' => 50102,
            'doc_date' => '2026-06-01',
            'department_code' => '4',
            'department_name' => 'Plant',
        ]);
        $this->createPurchaseOrder([
            'doc_date' => '2026-06-04',
            'pr_no' => '50101',
            'total_amount' => '100.00',
        ]);
        $this->createPurchaseOrder([
            'doc_date' => '2026-06-10',
            'pr_no' => '50102',
            'total_amount' => '200.00',
        ]);

        $data = app(ApprovalTurnaroundReport::class)->data([
            'from' => '2026-06-01',
            'to' => '2026-06-30',
            'project_code' => null,
            'dept_code' => null,
        ]);

        $this->assertSame(2, $data['summary']['document_count']);
        $this->assertSame('6.00', $data['summary']['average_days']);
        $this->assertSame('6.00', $data['summary']['median_days']);
        $this->assertSame(1, $data['summary']['buckets']['0_3']);
        $this->assertSame(0, $data['summary']['buckets']['4_7']);
        $this->assertSame(1, $data['summary']['buckets']['8_14']);
    }

    public function test_purchase_request_by_department_groups_rows(): void
    {
        $this->createPurchaseRequest([
            'doc_date' => '2026-06-03',
            'department_code' => '4',
            'department_name' => 'Plant',
            'project_code' => 'MBL',
            'total_amount' => '400.00',
        ]);
        $this->createPurchaseRequest([
            'doc_date' => '2026-06-04',
            'department_code' => '5',
            'department_name' => 'Logistics',
            'project_code' => 'MBL',
            'total_amount' => '600.00',
        ]);

        $data = app(PurchaseRequestByDepartmentReport::class)->data([
            'from' => '2026-06-01',
            'to' => '2026-06-30',
            'project_code' => null,
            'dept_code' => null,
        ]);

        $this->assertCount(2, $data['rows']);
        $this->assertSame(2, $data['summary']['document_count']);
        $this->assertSame('1000.00', $data['summary']['total_amount']);
    }

    public function test_csv_export_rows_match_on_screen_data(): void
    {
        $this->createPurchaseRequest([
            'doc_date' => '2026-06-08',
            'department_code' => '4',
            'department_name' => 'Plant',
            'pr_status' => 'O',
            'total_amount' => '1234.00',
        ]);

        $manager = $this->makeUserWithRole('procurement_manager');

        $response = $this->actingAsProject($manager)
            ->get('/reports/purchase-request-status/export/csv?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Plant', $content);
        $this->assertStringContainsString('1234.00', $content);
        $this->assertStringContainsString('Document Count', $content);
    }

    public function test_pdf_export_returns_pdf_bytes(): void
    {
        $this->createPurchaseOrder(['doc_date' => '2026-06-02', 'total_amount' => '50.00']);
        $manager = $this->makeUserWithRole('plant_manager');

        $response = $this->actingAsProject($manager)
            ->get('/reports/top-supplier/export/pdf?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_scoped_user_sees_only_own_project_on_report_and_export(): void
    {
        $this->createPurchaseRequest([
            'doc_date' => '2026-06-05',
            'project_code' => 'MBL',
            'total_amount' => '100.00',
        ]);
        $this->createPurchaseRequest([
            'doc_date' => '2026-06-05',
            'project_code' => '022C',
            'total_amount' => '9000.00',
        ]);

        $manager = $this->makeUserWithRole('plant_manager', 'MBL');

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($manager)
                ->get('/reports/purchase-request-by-department?from=2026-06-01&to=2026-06-30&project_code=022C')
                ->assertOk()
        );

        $this->assertSame(1, $page['props']['data']['summary']['document_count']);
        $this->assertSame('100.00', $page['props']['data']['summary']['total_amount']);

        $csv = $this->actingAsProject($manager)
            ->get('/reports/purchase-request-by-department/export/csv?from=2026-06-01&to=2026-06-30&project_code=022C')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('100.00', $csv);
        $this->assertStringNotContainsString('9000.00', $csv);
    }

    public function test_empty_period_returns_zero_rows_without_error(): void
    {
        $manager = $this->makeUserWithRole('procurement_manager');

        $page = $this->inertiaPageFrom(
            $this->actingAsProject($manager)
                ->get('/reports/purchase-order-trend?from=2020-01-01&to=2020-01-31')
                ->assertOk()
        );

        $this->assertSame([], $page['props']['data']['rows']);
        $this->assertSame(0, $page['props']['data']['summary']['document_count']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function procurementReportRoutes(): array
    {
        return [
            'purchase-request-status' => ['/reports/purchase-request-status'],
            'purchase-order-trend' => ['/reports/purchase-order-trend'],
            'top-supplier' => ['/reports/top-supplier'],
            'approval-turnaround' => ['/reports/approval-turnaround'],
            'purchase-request-by-department' => ['/reports/purchase-request-by-department'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPurchaseRequest(array $overrides = []): SapPurchaseRequest
    {
        static $sequence = 8100;
        $sequence++;
        $sapDocEntry = $overrides['sap_doc_entry'] ?? $sequence;

        $pr = SapPurchaseRequest::create(array_merge([
            'sap_doc_entry' => $sapDocEntry,
            'doc_num' => 600000 + $sequence,
            'doc_date' => '2026-03-10',
            'create_date' => '2026-03-10 10:00:00',
            'pr_type' => 'I',
            'department_code' => '4',
            'department_name' => 'Plant',
            'requester' => 'Requester Test',
            'mr_no' => 'MR-TEST',
            'required_date' => '2026-03-15',
            'remarks' => 'Test remarks',
            'pr_status' => 'O',
            'closed_status' => null,
            'line_count' => 1,
            'total_amount' => '750.00',
            'project_code' => 'MBL',
            'synced_at' => now(),
        ], $overrides));

        SapPurchaseRequestLine::create([
            'sap_purchase_request_id' => $pr->id,
            'sap_doc_entry' => $pr->sap_doc_entry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'ITEM-PR',
            'description' => 'Line item',
            'qty' => '1.00',
            'uom' => 'PCS',
            'unit_price' => '750.00',
            'line_amount' => '750.00',
            'line_vendor_code' => 'V001',
        ]);

        return $pr;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPurchaseOrder(array $overrides = []): SapPurchaseOrder
    {
        static $sequence = 9100;
        $sequence++;

        return SapPurchaseOrder::create(array_merge([
            'sap_doc_entry' => $sequence,
            'doc_num' => 800000 + $sequence,
            'doc_date' => '2026-03-10',
            'pr_no' => null,
            'vendor_code' => 'V001',
            'vendor_name' => 'Vendor Test',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '750.00',
            'vat_amount' => '0.00',
            'disc_amount' => '0.00',
            'delivery_status' => 'N',
            'budget_type' => 'OPEX',
            'origin' => 'sap',
            'synced_at' => now(),
        ], $overrides));
    }

    /**
     * @return array{component: string, props: array<string, mixed>}
     */
    private function inertiaPageFrom($response): array
    {
        $content = $response->getContent();
        $this->assertNotFalse(
            preg_match(
                '/<script data-page="app" type="application\/json">(.+?)<\/script>/',
                $content,
                $matches
            )
        );

        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        return $page;
    }
}
