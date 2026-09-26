<?php

namespace Tests\Feature\Procurement;

use App\Contracts\Sap\SapProcurementReadRepositoryContract;
use App\Jobs\SyncSapProcurementRegister;
use App\Models\SapSyncLog;
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
}
