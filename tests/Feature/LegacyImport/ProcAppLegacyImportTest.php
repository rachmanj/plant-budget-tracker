<?php

namespace Tests\Feature\LegacyImport;

use App\Models\DocumentAttachment;
use App\Models\ItemPrice;
use App\Models\SapPurchaseOrder;
use App\Services\LegacyImport\ProcAppAttachmentImporter;
use App\Services\LegacyImport\ProcAppLegacyImporter;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ProcAppLegacyImportTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    private string $importDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('local');
        $this->importDir = storage_path('framework/testing/legacy-import-'.uniqid());
        mkdir($this->importDir, 0777, true);
        mkdir($this->importDir.'/files', 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->importDir)) {
            $this->deleteDirectory($this->importDir);
        }

        parent::tearDown();
    }

    public function test_legacy_purchase_order_import_is_idempotent(): void
    {
        $this->writeCsv('purchase_orders.csv', [
            'id,sap_doc_entry,doc_num,vendor_name,project_code,dept_code,dept_name',
            '1,,990001,Legacy Vendor,MBL,40,Plant',
        ]);

        $importer = app(ProcAppLegacyImporter::class);
        $importer->import($this->importDir, false);
        $importer->import($this->importDir, false);

        $this->assertDatabaseCount('sap_purchase_orders', 1);
        $this->assertDatabaseHas('sap_purchase_orders', [
            'legacy_source' => 'proc_app',
            'legacy_doc_num' => '990001',
            'vendor_name' => 'Legacy Vendor',
        ]);
    }

    public function test_existing_register_purchase_order_is_not_overwritten(): void
    {
        SapPurchaseOrder::create([
            'sap_doc_entry' => 50001,
            'doc_num' => 880001,
            'doc_date' => '2026-01-01',
            'vendor_name' => 'SAP Vendor',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '100.00',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        $this->writeCsv('purchase_orders.csv', [
            'id,sap_doc_entry,doc_num,vendor_name,project_code',
            '9,50001,880001,Proc-app Vendor,MBL',
        ]);

        app(ProcAppLegacyImporter::class)->import($this->importDir, false);

        $this->assertDatabaseHas('sap_purchase_orders', [
            'sap_doc_entry' => 50001,
            'vendor_name' => 'SAP Vendor',
            'legacy_source' => null,
        ]);
    }

    public function test_legacy_purchase_order_lines_are_imported(): void
    {
        $this->writeCsv('purchase_orders.csv', [
            'id,sap_doc_entry,doc_num,project_code,dept_code,dept_name',
            '2,,990002,MBL,40,Plant',
        ]);
        $this->writeCsv('purchase_order_lines.csv', [
            'doc_num,line_num,vis_order,item_code,qty,unit_price,item_amount',
            '990002,0,0,SP-001,2,50000,100000',
        ]);

        app(ProcAppLegacyImporter::class)->import($this->importDir, false);

        $order = SapPurchaseOrder::query()->where('legacy_doc_num', '990002')->first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('sap_purchase_order_lines', [
            'sap_purchase_order_id' => $order->id,
            'item_code' => 'SP-001',
        ]);
        $this->assertSame('100000.00', (string) $order->fresh()->total_amount);
    }

    public function test_legacy_approvals_import_is_idempotent_and_visible_on_po_show(): void
    {
        SapPurchaseOrder::create([
            'sap_doc_entry' => 50002,
            'doc_num' => 770001,
            'doc_date' => '2026-02-01',
            'vendor_name' => 'Vendor',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '0.00',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        $this->writeCsv('purchase_order_approvals.csv', [
            'doc_num,level,decision,approver_name,acted_at',
            '770001,1,approved,Proc Manager,2025-12-01 10:00:00',
        ]);

        $importer = app(ProcAppLegacyImporter::class);
        $importer->import($this->importDir, false);
        $importer->import($this->importDir, false);

        $this->assertDatabaseCount('legacy_purchase_order_approvals', 1);

        $viewer = $this->makeUserWithRole('procurement_admin');
        $order = SapPurchaseOrder::query()->where('doc_num', 770001)->first();

        $this->actingAsProject($viewer)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('legacyApprovals', 1)
                ->where('legacyApprovals.0.decision', 'approved')
                ->where('legacyApprovals.0.approver_name', 'Proc Manager')
            );
    }

    public function test_attachment_import_copies_file_and_links_to_register(): void
    {
        $order = SapPurchaseOrder::create([
            'sap_doc_entry' => 50003,
            'doc_num' => 660001,
            'doc_date' => '2026-02-02',
            'vendor_name' => 'Vendor',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '0.00',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        $contents = 'legacy-pdf-bytes';
        file_put_contents($this->importDir.'/files/spec.pdf', $contents);

        $manifest = $this->importDir.'/manifest.csv';
        $this->writeCsvAt($manifest, [
            'doc_type,doc_num,original_name,size,mime,stored_path',
            'po,660001,spec.pdf,'.strlen($contents).',application/pdf,files/spec.pdf',
        ]);

        app(ProcAppAttachmentImporter::class)->import($manifest, $this->importDir, false);

        $attachment = DocumentAttachment::query()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('purchase_order', $attachment->attachable_type);
        $this->assertSame($order->id, $attachment->attachable_id);
        $this->assertFalse($attachment->file_unavailable);
        $this->assertSame(hash('sha256', $contents), $attachment->checksum);
        Storage::disk('local')->assertExists($attachment->stored_path);
    }

    public function test_attachment_import_is_idempotent(): void
    {
        SapPurchaseOrder::create([
            'sap_doc_entry' => 50004,
            'doc_num' => 660002,
            'doc_date' => '2026-02-02',
            'vendor_name' => 'Vendor',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '0.00',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        file_put_contents($this->importDir.'/files/note.pdf', 'note');
        $manifest = $this->importDir.'/manifest.csv';
        $this->writeCsvAt($manifest, [
            'doc_type,doc_num,original_name,size,mime,stored_path',
            'po,660002,note.pdf,4,application/pdf,files/note.pdf',
        ]);

        $importer = app(ProcAppAttachmentImporter::class);
        $importer->import($manifest, $this->importDir, false);
        $importer->import($manifest, $this->importDir, false);

        $this->assertDatabaseCount('document_attachments', 1);
    }

    public function test_missing_attachment_file_is_marked_unavailable_and_not_downloadable(): void
    {
        $order = SapPurchaseOrder::create([
            'sap_doc_entry' => 50005,
            'doc_num' => 660003,
            'doc_date' => '2026-02-02',
            'vendor_name' => 'Vendor',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '0.00',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        $manifest = $this->importDir.'/manifest.csv';
        $this->writeCsvAt($manifest, [
            'doc_type,doc_num,original_name,size,mime,stored_path',
            'po,660003,missing.pdf,99,application/pdf,files/missing.pdf',
        ]);

        app(ProcAppAttachmentImporter::class)->import($manifest, $this->importDir, false);

        $attachment = DocumentAttachment::query()->first();
        $this->assertTrue($attachment->file_unavailable);

        $viewer = $this->makeUserWithRole('procurement_admin');
        $this->actingAsProject($viewer)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('attachments', 1)
                ->where('attachments.0.file_unavailable', true)
                ->where('attachments.0.download_url', null)
            );

        $this->actingAsProject($viewer)
            ->get('/procurement/purchase-orders/'.$order->id.'/attachments/'.$attachment->id.'/download')
            ->assertNotFound();
    }

    public function test_attachment_manifest_without_register_match_is_recorded_as_failed(): void
    {
        $manifest = $this->importDir.'/manifest.csv';
        $this->writeCsvAt($manifest, [
            'doc_type,doc_num,original_name,size,mime,stored_path',
            'po,999999,orphan.pdf,10,application/pdf,files/orphan.pdf',
        ]);

        $summary = app(ProcAppAttachmentImporter::class)->import($manifest, $this->importDir, false);

        $this->assertSame(1, $summary->failed);
        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_item_price_import_does_not_replace_newer_pmb_price(): void
    {
        ItemPrice::create([
            'item_code' => 'HYD-001',
            'vendor_code' => 'V100',
            'price' => '250000.00',
            'currency' => 'IDR',
            'effective_date' => '2026-06-01',
            'source' => 'csv_import',
        ]);

        $this->writeCsv('item_prices.csv', [
            'item_code,vendor_code,price,effective_date',
            'HYD-001,V100,100000.00,2025-01-01',
        ]);

        app(ProcAppLegacyImporter::class)->import($this->importDir, false);

        $this->assertDatabaseHas('item_prices', [
            'item_code' => 'HYD-001',
            'vendor_code' => 'V100',
            'price' => '250000.00',
            'source' => 'csv_import',
        ]);
    }

    public function test_legacy_import_dry_run_does_not_persist_rows(): void
    {
        $this->writeCsv('purchase_orders.csv', [
            'id,doc_num,vendor_name,project_code,dept_code,dept_name',
            '3,990003,Dry Vendor,MBL,40,Plant',
        ]);

        Artisan::call('proc-app:import-legacy', [
            '--directory' => $this->importDir,
            '--dry-run' => true,
        ]);

        $this->assertDatabaseCount('sap_purchase_orders', 0);
    }

    public function test_procurement_admin_can_export_reports(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $admin = $this->makeUserWithRole('procurement_admin');
        $month = now()->format('Y-m');

        $this->actingAsProject($admin)
            ->get("/reports/budget-consumption/export/csv?project_code=MBL&month={$month}")
            ->assertOk();
    }

    public function test_planner_still_cannot_export_reports(): void
    {
        $finance = $this->makeFinanceDirector();
        $this->makeAllocation($finance);

        $planner = $this->makeUserWithRole('planner');
        $month = now()->format('Y-m');

        $this->actingAsProject($planner)
            ->get("/reports/budget-consumption/export/csv?project_code=MBL&month={$month}")
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function writeCsv(string $filename, array $lines): void
    {
        $this->writeCsvAt($this->importDir.'/'.$filename, $lines);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function writeCsvAt(string $path, array $lines): void
    {
        file_put_contents($path, implode("\n", $lines)."\n");
    }

    private function deleteDirectory(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
