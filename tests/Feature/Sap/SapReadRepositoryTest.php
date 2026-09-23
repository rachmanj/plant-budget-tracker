<?php

namespace Tests\Feature\Sap;

use App\Services\Sap\SapReadRepository;
use App\Services\Sap\SapService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SapReadRepositoryTest extends TestCase
{
    public function test_source_contains_t_sql_top_syntax_for_mr_lines_and_price_list(): void
    {
        $source = file_get_contents(app_path('Services/Sap/SapReadRepository.php'));

        $this->assertStringContainsString('SELECT TOP 100 * FROM [PRQ1]', $source);
        $this->assertStringContainsString('SELECT TOP 100 * FROM [ITM1]', $source);
        $this->assertStringContainsString('[DocEntry]', $source);
        $this->assertStringContainsString('[ItemCode]', $source);
    }

    public function test_get_material_request_lines_uses_direct_sql_first(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function ($sql, $bindings) {
                return str_contains($sql, 'SELECT TOP 100 * FROM [PRQ1]')
                    && $bindings === [500];
            })
            ->andReturn([(object) ['LineNum' => 0, 'ItemCode' => 'PN-1']]);

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldNotReceive('getEntity');

        $repo = new SapReadRepository($sapService);
        $lines = $repo->getMaterialRequestLines(500);

        $this->assertCount(1, $lines);
        $this->assertSame('PN-1', $lines->first()->ItemCode);
    }

    public function test_get_price_list_builds_in_clause_with_placeholders(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('select')
            ->once()
            ->withArgs(function ($sql, $bindings) {
                return str_contains($sql, 'SELECT TOP 100 * FROM [ITM1]')
                    && str_contains($sql, '[ItemCode] IN (?,?)')
                    && $bindings === ['A1', 'B2'];
            })
            ->andReturn([(object) ['ItemCode' => 'A1', 'Price' => 1000]]);

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $sapService = Mockery::mock(SapService::class);
        $repo = new SapReadRepository($sapService);

        $prices = $repo->getPriceList(['A1', 'B2']);

        $this->assertCount(1, $prices);
        $this->assertSame('A1', $prices->first()->ItemCode);
    }

    public function test_get_item_purchase_price_uses_latest_po_price_in_idr(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('selectOne')
            ->once()
            ->withArgs(function ($sql, $bindings) {
                return str_contains($sql, 'FROM [POR1]')
                    && str_contains($sql, 'INNER JOIN [OPOR]')
                    && $bindings === ['CO-VOE24070789', 'IDR'];
            })
            ->andReturn((object) [
                'Price' => 74253500,
                'Currency' => 'IDR',
                'DocNum' => 260206551,
                'DocDate' => '2026-09-22 00:00:00.000',
            ]);

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $sapService = Mockery::mock(SapService::class);
        $repo = new SapReadRepository($sapService);

        $result = $repo->getItemPurchasePrice('CO-VOE24070789');

        $this->assertSame('74253500.00', $result['price']);
        $this->assertSame('po', $result['source']);
        $this->assertSame('PO 260206551 · 2026-09-22', $result['reference']);
    }

    public function test_get_item_purchase_price_omits_date_when_doc_date_is_null(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('selectOne')
            ->once()
            ->andReturn((object) [
                'Price' => 74253500,
                'Currency' => 'IDR',
                'DocNum' => 260206551,
                'DocDate' => null,
            ]);

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $sapService = Mockery::mock(SapService::class);
        $repo = new SapReadRepository($sapService);

        $result = $repo->getItemPurchasePrice('CO-VOE24070789');

        $this->assertSame('PO 260206551', $result['reference']);
    }

    public function test_get_item_purchase_price_falls_back_to_item_master_when_no_idr_po_row(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('selectOne')
            ->once()
            ->withArgs(fn ($sql) => str_contains($sql, 'FROM [POR1]'))
            ->andReturn(null);
        $connection->shouldReceive('selectOne')
            ->once()
            ->withArgs(fn ($sql) => str_contains($sql, 'FROM [OITM]'))
            ->andReturn((object) ['LastPurPrc' => 88000]);

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $sapService = Mockery::mock(SapService::class);
        $repo = new SapReadRepository($sapService);

        $result = $repo->getItemPurchasePrice('PART-NO-PO');

        $this->assertSame('88000.00', $result['price']);
        $this->assertSame('item_master', $result['source']);
        $this->assertSame('Last purchase price (SAP item master)', $result['reference']);
    }

    public function test_get_item_purchase_price_returns_null_on_connection_failure(): void
    {
        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andThrow(new \RuntimeException('SSL certificate verify failed'));

        $sapService = Mockery::mock(SapService::class);
        $repo = new SapReadRepository($sapService);

        $this->assertNull($repo->getItemPurchasePrice('PART-X'));
    }

    public function test_get_material_request_falls_back_to_odata_when_sql_fails(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('table')
            ->once()
            ->with('OPRQ')
            ->andThrow(new \RuntimeException('connection unavailable'));

        DB::shouldReceive('connection')
            ->with('sap_sql')
            ->andReturn($connection);

        $sapService = Mockery::mock(SapService::class);
        $sapService->shouldReceive('request')
            ->once()
            ->with('GET', 'PurchaseRequests(321)')
            ->andReturn(['DocEntry' => 321, 'DocNum' => 99]);

        $repo = new SapReadRepository($sapService);
        $mr = $repo->getMaterialRequest(321);

        $this->assertSame(321, $mr['DocEntry']);
        $this->assertSame(99, $mr['DocNum']);
    }
}
