<?php

namespace Tests\Feature\Procurement;

use App\Models\DocumentAttachment;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PurchaseOrderAttachmentTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_valid_upload_persists_record_and_file_on_local_disk(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();
        $file = UploadedFile::fake()->create('purchase-spec.pdf', 120, 'application/pdf');

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/attachments', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $attachment = DocumentAttachment::query()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('purchase_order', $attachment->attachable_type);
        $this->assertSame($order->id, $attachment->attachable_id);
        $this->assertSame('purchase-spec.pdf', $attachment->original_name);
        $this->assertSame($admin->id, $attachment->uploaded_by);
        $this->assertNotNull($attachment->checksum);
        Storage::disk('local')->assertExists($attachment->stored_path);
    }

    public function test_forbidden_file_type_is_rejected_with_422(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();
        $file = UploadedFile::fake()->create('payload.exe', 50, 'application/x-msdownload');

        $this->actingAsProject($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/procurement/purchase-orders/'.$order->id.'/attachments', ['file' => $file])
            ->assertUnprocessable();

        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_file_larger_than_ten_megabytes_is_rejected_with_422(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();
        $file = UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf');

        $this->actingAsProject($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/procurement/purchase-orders/'.$order->id.'/attachments', ['file' => $file])
            ->assertUnprocessable();

        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_user_without_po_attach_cannot_upload(): void
    {
        $viewer = $this->makeUserWithRole('president_director');
        $order = $this->createPurchaseOrder();
        $file = UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf');

        $this->actingAsProject($viewer)
            ->post('/procurement/purchase-orders/'.$order->id.'/attachments', ['file' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_user_without_procurement_view_cannot_open_show_or_download(): void
    {
        $mechanic = $this->makeUserWithRole('mechanic');
        $order = $this->createPurchaseOrder();
        $attachment = $this->createAttachment($order);

        $this->actingAsProject($mechanic)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertForbidden();

        $this->actingAsProject($mechanic)
            ->get('/procurement/purchase-orders/'.$order->id.'/attachments/'.$attachment->id.'/download')
            ->assertForbidden();
    }

    public function test_download_returns_file_with_original_filename(): void
    {
        $viewer = $this->makeUserWithRole('president_director');
        $order = $this->createPurchaseOrder();
        $attachment = $this->createAttachment($order, 'signed-quote.pdf', 'pdf-content');

        $response = $this->actingAsProject($viewer)
            ->get('/procurement/purchase-orders/'.$order->id.'/attachments/'.$attachment->id.'/download');

        $response->assertOk();
        $response->assertDownload('signed-quote.pdf');
        $this->assertSame('pdf-content', $response->streamedContent());
    }

    public function test_attachment_on_another_purchase_order_returns_404(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $owner = $this->createPurchaseOrder(['doc_num' => 910001]);
        $other = $this->createPurchaseOrder(['doc_num' => 910002]);
        $attachment = $this->createAttachment($owner);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders/'.$other->id.'/attachments/'.$attachment->id.'/download')
            ->assertNotFound();

        $this->actingAsProject($admin)
            ->delete('/procurement/purchase-orders/'.$other->id.'/attachments/'.$attachment->id)
            ->assertNotFound();
    }

    public function test_delete_removes_database_row_and_stored_file(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();
        $attachment = $this->createAttachment($order);

        Storage::disk('local')->assertExists($attachment->stored_path);

        $this->actingAsProject($admin)
            ->delete('/procurement/purchase-orders/'.$order->id.'/attachments/'.$attachment->id)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('document_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->stored_path);
    }

    public function test_delete_without_po_attach_permission_is_forbidden(): void
    {
        $viewer = $this->makeUserWithRole('president_director');
        $order = $this->createPurchaseOrder();
        $attachment = $this->createAttachment($order);

        $this->actingAsProject($viewer)
            ->delete('/procurement/purchase-orders/'.$order->id.'/attachments/'.$attachment->id)
            ->assertForbidden();

        $this->assertDatabaseHas('document_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($attachment->stored_path);
    }

    public function test_show_page_includes_attachments_and_can_attach_props(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $viewer = $this->makeUserWithRole('plant_manager');
        $order = $this->createPurchaseOrder();
        $attachment = $this->createAttachment($order, 'packing-list.pdf');

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('attachments', 1)
                ->where('attachments.0.id', $attachment->id)
                ->where('attachments.0.original_name', 'packing-list.pdf')
                ->where('can.attach', true)
            );

        $this->actingAsProject($viewer)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('attachments', 1)
                ->where('can.attach', false)
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPurchaseOrder(array $overrides = []): SapPurchaseOrder
    {
        static $sequence = 12000;

        $sequence++;
        $sapDocEntry = $overrides['sap_doc_entry'] ?? $sequence;

        $order = SapPurchaseOrder::create(array_merge([
            'sap_doc_entry' => $sapDocEntry,
            'doc_num' => 200000 + $sequence,
            'doc_date' => '2026-03-10',
            'pr_no' => 'PR-ATTACH',
            'vendor_code' => 'V001',
            'vendor_name' => 'Vendor Test',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '500000.00',
            'vat_amount' => '0.00',
            'disc_amount' => '0.00',
            'delivery_status' => 'N',
            'budget_type' => 'OPEX',
            'origin' => 'sap',
            'synced_at' => now(),
        ], $overrides));

        SapPurchaseOrderLine::create([
            'sap_purchase_order_id' => $order->id,
            'sap_doc_entry' => $order->sap_doc_entry,
            'line_num' => 0,
            'vis_order' => 0,
            'item_code' => 'ITEM-1',
            'description' => 'Test item',
            'qty' => '1.00',
            'uom' => 'PCS',
            'unit_price' => '500000.00',
            'item_amount' => '500000.00',
            'project_code' => $order->project_code,
        ]);

        return $order;
    }

    private function createAttachment(
        SapPurchaseOrder $order,
        string $originalName = 'attachment.pdf',
        string $contents = 'attachment-bytes',
    ): DocumentAttachment {
        $storedPath = 'document-attachments/purchase_order/'.$order->id.'/test-'.$originalName;
        Storage::disk('local')->put($storedPath, $contents);

        return DocumentAttachment::create([
            'attachable_type' => 'purchase_order',
            'attachable_id' => $order->id,
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'mime' => 'application/pdf',
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'uploaded_by' => null,
        ]);
    }
}
