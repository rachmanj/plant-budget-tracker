<?php

namespace Tests\Feature\Procurement;

use App\Services\Sap\SapProcurementReadRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SapProcurementReadRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'procurement.po_department_codes' => ['40'],
        ]);
    }

    public function test_po_query_coalesces_local_currency_amounts_with_fc_fallback(): void
    {
        $source = file_get_contents(app_path('Services/Sap/SapProcurementReadRepository.php'));

        $this->assertStringContainsString(
            'COALESCE(NULLIF(A.[DocTotal], 0), A.[DocTotalFC]) AS total_amount',
            $source
        );
        $this->assertStringContainsString(
            'COALESCE(NULLIF(A.[VatSum], 0), A.[VatSumFC]) AS vat_amount',
            $source
        );
        $this->assertStringContainsString(
            'COALESCE(NULLIF(A.[DiscSum], 0), A.[DiscSumFC]) AS disc_amount',
            $source
        );
    }

    public function test_fetch_purchase_order_rows_returns_local_doc_total_when_fc_is_zero(): void
    {
        $from = Carbon::parse('2026-09-01')->startOfDay();
        $to = Carbon::parse('2026-09-30')->endOfDay();

        $connection = Mockery::mock();
        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function ($sql, $bindings) use ($from, $to) {
                return str_contains($sql, 'COALESCE(NULLIF(A.[DocTotal], 0), A.[DocTotalFC]) AS total_amount')
                    && $bindings[0] === $from->format('Y-m-d H:i:s')
                    && $bindings[1] === $to->format('Y-m-d H:i:s')
                    && $bindings[2] === '40';
            })
            ->andReturn([
                (object) [
                    'doc_num' => 260206644,
                    'total_amount' => 1123320,
                    'vat_amount' => 123456,
                    'disc_amount' => 0,
                ],
            ]);

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $rows = (new SapProcurementReadRepository())->fetchPurchaseOrderRows($from, $to);

        $this->assertCount(1, $rows);
        $this->assertSame(1123320, $rows->first()->total_amount);
        $this->assertSame(123456, $rows->first()->vat_amount);
    }

    public function test_fetch_purchase_order_rows_returns_fc_amounts_when_local_columns_are_zero(): void
    {
        $from = Carbon::parse('2026-09-01')->startOfDay();
        $to = Carbon::parse('2026-09-30')->endOfDay();

        $connection = Mockery::mock();
        $connection->shouldReceive('select')
            ->once()
            ->andReturn([
                (object) [
                    'doc_num' => 990001,
                    'total_amount' => 50000,
                    'vat_amount' => 5000,
                    'disc_amount' => 250,
                ],
            ]);

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $rows = (new SapProcurementReadRepository())->fetchPurchaseOrderRows($from, $to);

        $this->assertSame(50000, $rows->first()->total_amount);
        $this->assertSame(5000, $rows->first()->vat_amount);
        $this->assertSame(250, $rows->first()->disc_amount);
    }
}
