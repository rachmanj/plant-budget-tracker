<?php

namespace Tests\Feature\Procurement;

use App\Contracts\Sap\SapProcurementReadRepositoryContract;
use App\Jobs\SyncSapProcurementRegister;
use App\Models\PlantRequest;
use App\Models\SapSyncLog;
use App\Models\TabulationBid;
use App\Services\Procurement\PurchaseOrderRegisterSync;
use App\Services\Procurement\PurchaseRequestRegisterSync;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SyncSapProcurementRegisterJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_job_writes_success_log_and_skips_repeat_window(): void
    {
        $from = Carbon::parse('2026-08-01')->startOfDay();
        $to = Carbon::parse('2026-08-31')->endOfDay();

        $this->mock(SapProcurementReadRepositoryContract::class, function ($mock) {
            $mock->shouldReceive('fetchPurchaseOrderRows')->once()->andReturn(collect());
            $mock->shouldReceive('fetchPurchaseRequestRows')->once()->andReturn(collect());
        });

        $job = new SyncSapProcurementRegister($from, $to);
        $job->handle(
            app(SapProcurementReadRepositoryContract::class),
            app(\App\Services\Procurement\PurchaseOrderRegisterSync::class),
            app(\App\Services\Procurement\PurchaseRequestRegisterSync::class),
        );

        $log = SapSyncLog::query()
            ->where('correlation_key', 'sync_pr_register:2026-08-01:2026-08-31')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertNotNull($log->completed_at);

        $job->handle(
            app(SapProcurementReadRepositoryContract::class),
            app(\App\Services\Procurement\PurchaseOrderRegisterSync::class),
            app(\App\Services\Procurement\PurchaseRequestRegisterSync::class),
        );

        $this->assertSame(1, SapSyncLog::query()->count());
    }

    public function test_job_passes_pmb_document_numbers_to_repository_include_lists(): void
    {
        $from = Carbon::parse('2026-09-01')->startOfDay();
        $to = Carbon::parse('2026-09-30')->endOfDay();

        $firstPlantRequest = PlantRequest::factory()->create(['sap_pr_no' => '901001', 'sap_po_id' => '602001']);
        PlantRequest::factory()->create([
            'budget_allocation_id' => $firstPlantRequest->budget_allocation_id,
            'equipment_id' => $firstPlantRequest->equipment_id,
            'unit_code_cache' => 'E-902',
            'sap_pr_no' => '901002',
            'sap_po_id' => null,
            'requested_by' => $firstPlantRequest->requested_by,
        ]);
        TabulationBid::factory()->create(['sap_po_id' => '603001']);

        $this->mock(SapProcurementReadRepositoryContract::class, function ($mock) {
            $mock->shouldReceive('fetchPurchaseOrderRows')
                ->once()
                ->withArgs(function ($jobFrom, $jobTo, array $includeDocEntries) {
                    sort($includeDocEntries);

                    return $includeDocEntries === ['602001', '603001'];
                })
                ->andReturn(collect());

            $mock->shouldReceive('fetchPurchaseRequestRows')
                ->once()
                ->withArgs(function ($jobFrom, $jobTo, array $includeDocNums) {
                    sort($includeDocNums);

                    return $includeDocNums === ['901001', '901002'];
                })
                ->andReturn(collect());
        });

        $job = new SyncSapProcurementRegister($from, $to);
        $job->handle(
            app(SapProcurementReadRepositoryContract::class),
            app(\App\Services\Procurement\PurchaseOrderRegisterSync::class),
            app(\App\Services\Procurement\PurchaseRequestRegisterSync::class),
        );
    }

    public function test_job_forwards_include_lists_to_register_sync_services(): void
    {
        $from = Carbon::parse('2026-09-15')->startOfDay();
        $to = Carbon::parse('2026-09-16')->endOfDay();

        PlantRequest::factory()->create(['sap_pr_no' => '260100451', 'sap_po_id' => '88001']);

        $emptySummary = [
            'documents_created' => 0,
            'documents_updated' => 0,
            'lines_created' => 0,
            'lines_updated' => 0,
            'documents_matched_by_doc_num' => 0,
            'entry_conflicts_skipped' => 0,
            'duplicate_retries' => 0,
            'documents_included_by_doc_num' => 0,
            'documents_included_by_entry' => 0,
        ];

        $this->mock(SapProcurementReadRepositoryContract::class, function ($mock) {
            $mock->shouldReceive('fetchPurchaseOrderRows')->once()->andReturn(collect());
            $mock->shouldReceive('fetchPurchaseRequestRows')->once()->andReturn(collect());
        });

        $this->mock(PurchaseOrderRegisterSync::class, function ($mock) use ($emptySummary) {
            $mock->shouldReceive('sync')
                ->once()
                ->withArgs(function ($rows, array $includeDocEntries) {
                    sort($includeDocEntries);

                    return $includeDocEntries === ['88001'];
                })
                ->andReturn($emptySummary);
        });

        $this->mock(PurchaseRequestRegisterSync::class, function ($mock) use ($emptySummary) {
            $mock->shouldReceive('sync')
                ->once()
                ->withArgs(function ($rows, array $includeDocNums) {
                    sort($includeDocNums);

                    return $includeDocNums === ['260100451'];
                })
                ->andReturn($emptySummary);
        });

        $job = new SyncSapProcurementRegister($from, $to);
        $job->handle(
            app(SapProcurementReadRepositoryContract::class),
            app(PurchaseOrderRegisterSync::class),
            app(PurchaseRequestRegisterSync::class),
        );
    }
}
