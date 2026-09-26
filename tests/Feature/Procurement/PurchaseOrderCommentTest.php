<?php

namespace Tests\Feature\Procurement;

use App\Models\DocumentComment;
use App\Models\DocumentCommentMention;
use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class PurchaseOrderCommentTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_valid_comment_is_stored_and_visible_on_show(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', [
                'body' => 'Please confirm delivery schedule.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('document_comments', [
            'commentable_type' => 'purchase_order',
            'commentable_id' => $order->id,
            'user_id' => $admin->id,
            'body' => 'Please confirm delivery schedule.',
        ]);

        $this->actingAsProject($admin)
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('comments', 1)
                ->where('comments.0.body', 'Please confirm delivery schedule.')
                ->where('comments.0.author_name', $admin->name)
                ->where('can.comment', true)
            );
    }

    public function test_empty_body_is_rejected_with_422(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();

        $this->actingAsProject($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', ['body' => ''])
            ->assertUnprocessable();

        $this->assertDatabaseCount('document_comments', 0);
    }

    public function test_user_without_po_comment_cannot_post(): void
    {
        $viewer = $this->makeUserWithRole('president_director');
        $order = $this->createPurchaseOrder();

        $this->actingAsProject($viewer)
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertDatabaseCount('document_comments', 0);
    }

    public function test_mention_is_stored_for_matching_user(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $mentioned = User::factory()->create([
            'is_active' => true,
            'email' => 'buyer.colleague@example.com',
            'project_code_scope' => 'MBL',
        ]);
        setPermissionsTeamId('MBL');
        $mentioned->assignRole('buyer');

        $order = $this->createPurchaseOrder();

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', [
                'body' => 'Please review @buyer.colleague',
            ])
            ->assertRedirect();

        $comment = DocumentComment::query()->first();
        $this->assertNotNull($comment);
        $this->assertDatabaseHas('document_comment_mentions', [
            'document_comment_id' => $comment->id,
            'user_id' => $mentioned->id,
        ]);
    }

    public function test_duplicate_mention_for_same_user_is_not_doubled(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $mentioned = User::factory()->create([
            'is_active' => true,
            'email' => 'dup.user@example.com',
            'project_code_scope' => 'MBL',
        ]);
        setPermissionsTeamId('MBL');
        $mentioned->assignRole('buyer');

        $order = $this->createPurchaseOrder();

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', [
                'body' => 'Hi @dup.user and again @dup.user',
            ])
            ->assertRedirect();

        $comment = DocumentComment::query()->first();
        $this->assertNotNull($comment);
        $this->assertSame(
            1,
            DocumentCommentMention::query()->where('document_comment_id', $comment->id)->count()
        );
    }

    public function test_mention_to_plant_user_in_other_project_team_is_stored_and_shown(): void
    {
        $admin = $this->makeGlobalUserWithRole('procurement_admin');
        $globalBuyer = User::factory()->create([
            'is_active' => true,
            'email' => 'buyer.mention@example.com',
            'project_code_scope' => null,
        ]);
        setPermissionsTeamId('');
        $globalBuyer->assignRole('buyer');

        $plantManager = User::factory()->create([
            'is_active' => true,
            'name' => 'Plant Manager 022C',
            'email' => 'plant.manager@pmb.demo',
            'project_code_scope' => '022C',
        ]);
        setPermissionsTeamId('022C');
        $plantManager->assignRole('plant_manager');

        $order = $this->createPurchaseOrder(['project_code' => 'MBL']);

        $this->actingAs($admin)
            ->withoutVite()
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', [
                'body' => 'Please align @buyer.mention and @plant.manager',
            ])
            ->assertRedirect();

        $comment = DocumentComment::query()->first();
        $this->assertNotNull($comment);
        $this->assertDatabaseHas('document_comment_mentions', [
            'document_comment_id' => $comment->id,
            'user_id' => $globalBuyer->id,
        ]);
        $this->assertDatabaseHas('document_comment_mentions', [
            'document_comment_id' => $comment->id,
            'user_id' => $plantManager->id,
        ]);

        $this->actingAs($admin)
            ->withoutVite()
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('comments.0.mentioned_names', function ($names) use ($globalBuyer) {
                    $list = collect($names)->all();

                    return count($list) === 2
                        && in_array('Plant Manager 022C', $list, true)
                        && in_array($globalBuyer->name, $list, true);
                })
            );
    }

    public function test_mentionable_users_includes_plant_users_for_global_comment_author(): void
    {
        $admin = $this->makeGlobalUserWithRole('procurement_admin');
        $plantManager = User::factory()->create([
            'is_active' => true,
            'email' => 'plant.manager@pmb.demo',
            'project_code_scope' => '022C',
        ]);
        setPermissionsTeamId('022C');
        $plantManager->assignRole('plant_manager');

        $order = $this->createPurchaseOrder();

        $this->actingAs($admin)
            ->withoutVite()
            ->get('/procurement/purchase-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('mentionableUsers')
                ->where('mentionableUsers', fn ($users) => collect($users)->contains(
                    fn (array $row) => $row['email'] === 'plant.manager@pmb.demo'
                ))
            );
    }

    public function test_global_author_duplicate_mentions_are_not_doubled_in_database(): void
    {
        $admin = $this->makeGlobalUserWithRole('procurement_admin');
        $plantManager = User::factory()->create([
            'is_active' => true,
            'email' => 'plant.manager@pmb.demo',
            'project_code_scope' => '022C',
        ]);
        setPermissionsTeamId('022C');
        $plantManager->assignRole('plant_manager');

        $order = $this->createPurchaseOrder();

        $this->actingAs($admin)
            ->withoutVite()
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', [
                'body' => '@plant.manager please see @plant.manager',
            ])
            ->assertRedirect();

        $comment = DocumentComment::query()->first();
        $this->assertNotNull($comment);
        $this->assertSame(
            1,
            DocumentCommentMention::query()->where('document_comment_id', $comment->id)->count()
        );
    }

    public function test_mention_resolution_preserves_comment_author_permission_team_context(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $admin = $this->makeGlobalUserWithRole('procurement_admin');

        $plantManager = User::factory()->create([
            'is_active' => true,
            'email' => 'plant.manager@pmb.demo',
            'project_code_scope' => '022C',
        ]);
        setPermissionsTeamId('022C');
        $plantManager->assignRole('plant_manager');

        setPermissionsTeamId('');
        $admin->unsetRelation('roles');
        $admin->unsetRelation('permissions');
        $teamBefore = $registrar->getPermissionsTeamId();
        $canCommentBefore = $admin->can('po.comment');

        $order = $this->createPurchaseOrder();

        $this->actingAs($admin)
            ->withoutVite()
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', [
                'body' => 'FYI @plant.manager',
            ])
            ->assertRedirect();

        $teamAfter = $registrar->getPermissionsTeamId();
        $admin->unsetRelation('roles');
        $admin->unsetRelation('permissions');
        setPermissionsTeamId($teamBefore);
        $canCommentAfter = $admin->can('po.comment');

        $this->assertSame('', $teamBefore);
        $this->assertSame($teamBefore, $teamAfter);
        $this->assertTrue($canCommentBefore);
        $this->assertTrue($canCommentAfter);
    }

    public function test_unknown_mention_is_ignored_without_failing(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$order->id.'/comments', [
                'body' => 'Ping @nobody_here_xyz',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('document_comment_mentions', 0);
    }

    public function test_author_can_delete_own_comment(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin');
        $order = $this->createPurchaseOrder();
        $comment = DocumentComment::create([
            'commentable_type' => 'purchase_order',
            'commentable_id' => $order->id,
            'user_id' => $admin->id,
            'body' => 'Temporary note',
        ]);

        $this->actingAsProject($admin)
            ->delete('/procurement/purchase-orders/'.$order->id.'/comments/'.$comment->id)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('document_comments', ['id' => $comment->id]);
    }

    public function test_other_user_cannot_delete_comment_without_user_manage(): void
    {
        $author = $this->makeUserWithRole('procurement_admin');
        $other = $this->makeUserWithRole('buyer');
        $order = $this->createPurchaseOrder();
        $comment = DocumentComment::create([
            'commentable_type' => 'purchase_order',
            'commentable_id' => $order->id,
            'user_id' => $author->id,
            'body' => 'Author only',
        ]);

        $this->actingAsProject($other)
            ->delete('/procurement/purchase-orders/'.$order->id.'/comments/'.$comment->id)
            ->assertForbidden();

        $this->assertDatabaseHas('document_comments', ['id' => $comment->id]);
    }

    public function test_user_with_user_manage_can_delete_another_users_comment(): void
    {
        $author = $this->makeUserWithRole('procurement_admin');
        $manager = $this->makeGlobalUserWithRole('it_manager');
        $order = $this->createPurchaseOrder();
        $comment = DocumentComment::create([
            'commentable_type' => 'purchase_order',
            'commentable_id' => $order->id,
            'user_id' => $author->id,
            'body' => 'Moderated',
        ]);

        $this->actingAs($manager)
            ->withoutVite()
            ->withSession(['current_project' => 'MBL'])
            ->delete('/procurement/purchase-orders/'.$order->id.'/comments/'.$comment->id)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('document_comments', ['id' => $comment->id]);
    }

    public function test_scoped_user_gets_forbidden_when_commenting_on_other_project_po(): void
    {
        $manager = $this->makeUserWithRole('project_manager', 'MBL');
        $otherOrder = $this->createPurchaseOrder(['project_code' => '022C']);

        $this->actingAsProject($manager)
            ->post('/procurement/purchase-orders/'.$otherOrder->id.'/comments', ['body' => 'Out of scope'])
            ->assertForbidden();

        $this->assertDatabaseCount('document_comments', 0);
    }

    public function test_scoped_user_gets_forbidden_when_uploading_attachment_on_other_project_po(): void
    {
        $admin = $this->makeUserWithRole('procurement_admin', 'MBL');
        $otherOrder = $this->createPurchaseOrder(['project_code' => '022C']);
        $file = UploadedFile::fake()->create('scope.pdf', 40, 'application/pdf');

        $this->actingAsProject($admin)
            ->post('/procurement/purchase-orders/'.$otherOrder->id.'/attachments', ['file' => $file])
            ->assertForbidden();

        $this->assertDatabaseCount('document_attachments', 0);
    }

    private function makeGlobalUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => null,
        ]);
        setPermissionsTeamId('');
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPurchaseOrder(array $overrides = []): SapPurchaseOrder
    {
        static $sequence = 13000;

        $sequence++;
        $sapDocEntry = $overrides['sap_doc_entry'] ?? $sequence;

        $order = SapPurchaseOrder::create(array_merge([
            'sap_doc_entry' => $sapDocEntry,
            'doc_num' => 200000 + $sequence,
            'doc_date' => '2026-03-10',
            'pr_no' => 'PR-COMMENT',
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
            'item_code' => 'ITEM-C',
            'description' => 'Test item',
            'qty' => '1.00',
            'uom' => 'PCS',
            'unit_price' => '500000.00',
            'item_amount' => '500000.00',
            'project_code' => $order->project_code,
        ]);

        return $order;
    }
}
