<?php

namespace Tests\Feature\Procurement;

use App\Jobs\SyncSapSuppliers;
use App\Models\ItemPrice;
use App\Models\ItemPriceHistory;
use App\Models\ItemPriceImport;
use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Models\SapSupplier;
use App\Services\Pricing\ItemPriceCsvImporter;
use App\Services\Pricing\PricingEstimator;
use App\Services\Procurement\SapSupplierSync;
use App\Services\Sap\SapReadRepository;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class Phase3SupplierAndItemPriceTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('local');
        Cache::flush();
    }

    public function test_supplier_sync_is_idempotent_and_maps_columns(): void
    {
        $rows = collect([
            (object) [
                'card_code' => 'V-001',
                'card_name' => 'Vendor One',
                'payment_terms' => 'Net 30',
                'currency' => 'IDR',
            ],
        ]);

        $sync = app(SapSupplierSync::class);
        $first = $sync->sync($rows);
        $second = $sync->sync($rows);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(1, SapSupplier::query()->count());

        $supplier = SapSupplier::query()->where('card_code', 'V-001')->first();
        $this->assertSame('Vendor One', $supplier->card_name);
        $this->assertSame('Net 30', $supplier->payment_terms);
        $this->assertSame('IDR', $supplier->currency);
        $this->assertTrue($supplier->is_active);
    }

    public function test_supplier_missing_from_sap_sync_is_marked_inactive_not_deleted(): void
    {
        SapSupplier::query()->create([
            'card_code' => 'V-OLD',
            'card_name' => 'Legacy Vendor',
            'is_active' => true,
            'synced_at' => now()->subDay(),
        ]);

        $sync = app(SapSupplierSync::class);
        $sync->sync(collect([
            (object) [
                'card_code' => 'V-NEW',
                'card_name' => 'Active Vendor',
                'payment_terms' => null,
                'currency' => 'IDR',
            ],
        ]));

        $this->assertSame(2, SapSupplier::query()->count());
        $old = SapSupplier::query()->where('card_code', 'V-OLD')->first();
        $this->assertFalse($old->is_active);
        $this->assertTrue(SapSupplier::query()->where('card_code', 'V-NEW')->value('is_active'));
    }

    public function test_sync_sap_suppliers_job_uses_repository_rows(): void
    {
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('fetchVendors')->once()->andReturn(collect([
                (object) [
                    'card_code' => 'V-JOB',
                    'card_name' => 'Job Vendor',
                    'payment_terms' => 'Cash',
                    'currency' => 'IDR',
                ],
            ]));
        });

        $summary = app(SyncSapSuppliers::class)->handle(
            app(SapReadRepository::class),
            app(SapSupplierSync::class),
        );

        $this->assertSame(1, $summary['created']);
        $this->assertDatabaseHas('sap_suppliers', ['card_code' => 'V-JOB', 'is_active' => true]);
    }

    public function test_csv_import_creates_price_and_history_rows(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $csv = "item_code,price,vendor_code\nPART-CSV,150000.50,V1\n";

        $import = app(ItemPriceCsvImporter::class)->import(
            UploadedFile::fake()->createWithContent('prices.csv', $csv),
            $admin,
        );

        $this->assertSame(1, $import->rows_created);
        $this->assertSame(0, $import->rows_updated);
        $this->assertDatabaseHas('item_prices', [
            'item_code' => 'PART-CSV',
            'vendor_code' => 'V1',
            'price' => '150000.50',
        ]);
        $this->assertSame(1, ItemPriceHistory::query()->count());
    }

    public function test_importing_same_csv_twice_yields_no_new_rows_or_history(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $csv = "item_code,price\nPART-DUP,1000.00\n";
        $file = UploadedFile::fake()->createWithContent('dup.csv', $csv);
        $importer = app(ItemPriceCsvImporter::class);

        $first = $importer->import($file, $admin);
        $second = $importer->import(
            UploadedFile::fake()->createWithContent('dup.csv', $csv),
            $admin,
        );

        $this->assertSame(1, $first->rows_created);
        $this->assertSame(0, $first->rows_unchanged);
        $this->assertSame(0, $second->rows_created);
        $this->assertSame(0, $second->rows_updated);
        $this->assertSame(1, $second->rows_unchanged);
        $this->assertSame(1, ItemPrice::query()->count());
        $this->assertSame(1, ItemPriceHistory::query()->count());
    }

    public function test_price_change_creates_history_with_old_and_new_values(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $importer = app(ItemPriceCsvImporter::class);
        $importer->import(
            UploadedFile::fake()->createWithContent('a.csv', "item_code,price\nPART-CHG,100.00\n"),
            $admin,
        );
        $importer->import(
            UploadedFile::fake()->createWithContent('b.csv', "item_code,price\nPART-CHG,250.00\n"),
            $admin,
        );

        $history = ItemPriceHistory::query()->orderByDesc('id')->first();
        $this->assertSame('100.00', number_format((float) $history->old_price, 2, '.', ''));
        $this->assertSame('250.00', number_format((float) $history->new_price, 2, '.', ''));
        $this->assertSame(2, ItemPriceHistory::query()->count());
    }

    public function test_invalid_rows_are_recorded_in_errors_without_aborting_import(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $csv = "item_code,price\nPART-OK,100\n,bad\nPART-NEG,-5\n";

        $import = app(ItemPriceCsvImporter::class)->import(
            UploadedFile::fake()->createWithContent('mixed.csv', $csv),
            $admin,
        );

        $this->assertSame(1, $import->rows_created);
        $this->assertSame(2, $import->rows_failed);
        $this->assertCount(2, $import->errors);
        $this->assertDatabaseHas('item_prices', ['item_code' => 'PART-OK']);
    }

    public function test_semicolon_delimiter_is_recognized(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $csv = "item_code;price\nPART-SEMI;999.00\n";

        $import = app(ItemPriceCsvImporter::class)->import(
            UploadedFile::fake()->createWithContent('semi.csv', $csv),
            $admin,
        );

        $this->assertSame(1, $import->rows_created);
        $this->assertDatabaseHas('item_prices', ['item_code' => 'PART-SEMI', 'price' => '999.00']);
    }

    public function test_non_csv_upload_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only CSV files are accepted.');

        app(ItemPriceCsvImporter::class)->import(
            UploadedFile::fake()->create('notes.txt', 'item_code,price'),
            $admin,
        );
    }

    public function test_import_route_returns_403_without_item_price_import_permission(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $csv = UploadedFile::fake()->createWithContent('prices.csv', "item_code,price\nA,1\n");

        $response = $this->actingAs($buyer)->post(route('procurement.item-prices.import'), [
            'file' => $csv,
        ]);

        $response->assertForbidden();
    }

    public function test_estimator_prefers_item_master_before_plant_request_history(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $request = PlantRequest::factory()->create([
            'status' => 'approved',
            'requested_by' => $buyer->id,
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $request->id,
            'part_number' => 'PART-HIER',
            'unit_price_est' => '50000.00',
            'price_source' => 'manual',
        ]);

        ItemPrice::query()->create([
            'item_code' => 'PART-HIER',
            'vendor_code' => '',
            'price' => '175000.00',
            'currency' => 'IDR',
            'source' => 'csv_import',
        ]);

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getLastPoItemPrice')->andReturn(null);
        });

        $result = app(PricingEstimator::class)->estimate('PART-HIER');

        $this->assertSame('175000.00', $result['unit_price']);
        $this->assertSame('item_master', $result['source']);
        $this->assertStringContainsString('Item master', $result['reference']);
    }

    public function test_estimator_cache_is_cleared_after_import(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('getLastPoItemPrice')->andReturn(null);
        });

        $estimator = app(PricingEstimator::class);
        $before = $estimator->estimate('PART-CACHE');
        $this->assertSame('0.00', $before['unit_price']);

        app(ItemPriceCsvImporter::class)->import(
            UploadedFile::fake()->createWithContent('cache.csv', "item_code,price\nPART-CACHE,42000.00\n"),
            $admin,
        );

        $after = $estimator->estimate('PART-CACHE');
        $this->assertSame('42000.00', $after['unit_price']);
        $this->assertSame('item_master', $after['source']);
    }

    public function test_empty_vendor_code_allows_distinct_items_without_overwriting(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $csv = "item_code,price,vendor_code\nITEM-A,100,\nITEM-B,200,\n";

        app(ItemPriceCsvImporter::class)->import(
            UploadedFile::fake()->createWithContent('two.csv', $csv),
            $admin,
        );

        $this->assertSame(2, ItemPrice::query()->count());
        $this->assertSame('100.00', ItemPrice::query()->where('item_code', 'ITEM-A')->value('price'));
        $this->assertSame('200.00', ItemPrice::query()->where('item_code', 'ITEM-B')->value('price'));
    }

    public function test_import_route_returns_summary_for_authorized_user(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $csv = UploadedFile::fake()->createWithContent('prices.csv', "item_code,price\nROUTE-1,10.00\n");

        $response = $this->actingAs($admin)->post(route('procurement.item-prices.import'), [
            'file' => $csv,
        ]);

        $response->assertOk()
            ->assertJsonPath('rows_created', 1)
            ->assertJsonPath('rows_total', 1);
    }

    public function test_suppliers_index_allows_procurement_admin_and_denies_mechanic(): void
    {
        $this->actingAsProject($this->makeUserWithRole('procurement_admin'))
            ->get(route('procurement.suppliers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Procurement/Suppliers/Index', false));

        $this->actingAsProject($this->makeUserWithRole('mechanic'))
            ->get(route('procurement.suppliers.index'))
            ->assertForbidden();
    }

    public function test_item_prices_index_allows_procurement_admin_and_denies_mechanic(): void
    {
        $this->actingAsProject($this->makeUserWithRole('procurement_admin'))
            ->get(route('procurement.item-prices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Procurement/ItemPrices/Index', false));

        $this->actingAsProject($this->makeUserWithRole('mechanic'))
            ->get(route('procurement.item-prices.index'))
            ->assertForbidden();
    }

    public function test_suppliers_sync_dispatches_job_and_denies_without_procurement_sync(): void
    {
        Queue::fake();

        $this->mock(SapReadRepository::class, function ($mock) {
            $mock->shouldReceive('fetchVendors')->andReturn(collect());
        });

        $admin = $this->makeUserWithRole('procurement_admin');
        $this->actingAsProject($admin)
            ->post(route('procurement.suppliers.sync'))
            ->assertRedirect(route('procurement.suppliers.index'));

        Queue::assertPushed(SyncSapSuppliers::class);

        $buyer = $this->makeUserWithRole('buyer');
        $this->actingAsProject($buyer)
            ->post(route('procurement.suppliers.sync'))
            ->assertForbidden();
    }

    public function test_item_prices_page_hides_import_for_users_without_item_price_import(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $this->actingAsProject($buyer)
            ->get(route('procurement.item-prices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.import', false));
    }

    public function test_item_price_template_download_returns_csv_header(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');

        $response = $this->actingAsProject($admin)
            ->get(route('procurement.item-prices.template'));

        $response->assertOk();
        $this->assertStringContainsString(
            'item_code,vendor_code,uom,price,effective_date,note',
            $response->streamedContent()
        );
    }

    public function test_item_prices_page_includes_import_history(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        ItemPriceImport::query()->create([
            'original_name' => 'batch.csv',
            'stored_path' => 'item-price-imports/test.csv',
            'rows_total' => 3,
            'rows_created' => 2,
            'rows_updated' => 1,
            'rows_unchanged' => 0,
            'rows_failed' => 0,
            'errors' => [],
            'imported_by' => $admin->id,
            'imported_at' => now(),
        ]);

        $this->actingAsProject($admin)
            ->get(route('procurement.item-prices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('importHistory.data', 1)
                ->where('importHistory.data.0.original_name', 'batch.csv')
            );
    }

    public function test_plant_request_show_includes_line_price_histories_when_present(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');
        $admin = $this->makeUserWithRole('procurement_admin');

        app(ItemPriceCsvImporter::class)->import(
            UploadedFile::fake()->createWithContent('hist.csv', "item_code,price\nPART-HIST-SHOW,5000.00\n"),
            $admin,
        );

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $plantRequest->id,
            'part_number' => 'PART-HIST-SHOW',
            'unit_price_est' => '5000.00',
        ]);

        $this->actingAsProject($planner)
            ->get(route('plant-requests.show', $plantRequest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('linePriceHistories.PART-HIST-SHOW', 1)
                ->where('linePriceHistories.PART-HIST-SHOW.0.new_price', '5000.00')
            );
    }

    public function test_plant_request_show_has_empty_line_price_histories_without_master_changes(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);
        $planner = $this->makeUserWithRole('planner');

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'requested_by' => $planner->id,
            'status' => 'draft',
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $plantRequest->id,
            'part_number' => 'PART-NO-HIST',
        ]);

        $this->actingAsProject($planner)
            ->get(route('plant-requests.show', $plantRequest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('linePriceHistories', []));
    }
}
