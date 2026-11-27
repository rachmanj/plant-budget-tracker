<?php

namespace Tests\Feature\Dashboard;

use App\Models\SapPurchaseOrder;
use App\Models\User;
use App\Services\Dashboard\RegisterMetrics;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class RegisterMetricsTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_user_without_register_permission_receives_null_register_on_dashboard(): void
    {
        $bareUser = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => 'MBL',
        ]);
        setPermissionsTeamId('MBL');

        $this->actingAs($bareUser)
            ->withoutVite()
            ->withSession(['current_project' => 'MBL'])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard', false)
                ->where('register', null)
            );
    }

    public function test_authorized_user_receives_full_register_structure(): void
    {
        $manager = $this->makeUserWithRole('procurement_manager');

        SapPurchaseOrder::create([
            'sap_doc_entry' => 501,
            'doc_num' => '900501',
            'doc_date' => now()->toDateString(),
            'vendor_code' => 'V001',
            'vendor_name' => 'Vendor Test',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '1000.00',
            'vat_amount' => '0.00',
            'disc_amount' => '0.00',
            'delivery_status' => 'N',
            'budget_type' => 'OPEX',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        $this->actingAsProject($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard', false)
                ->has('register.totals')
                ->has('register.monthly', 6)
                ->has('register.projects')
                ->has('register.recent')
                ->has('register.health')
            );
    }

    public function test_monthly_series_includes_months_without_documents_as_zero(): void
    {
        $this->travelTo('2026-08-15 09:00:00');

        SapPurchaseOrder::create([
            'sap_doc_entry' => 502,
            'doc_num' => '900502',
            'doc_date' => '2026-08-10',
            'vendor_code' => 'V001',
            'vendor_name' => 'Vendor Test',
            'project_code' => 'MBL',
            'dept_code' => '40',
            'dept_name' => 'Plant',
            'currency' => 'IDR',
            'total_amount' => '500.00',
            'vat_amount' => '0.00',
            'disc_amount' => '0.00',
            'delivery_status' => 'N',
            'budget_type' => 'OPEX',
            'origin' => 'sap',
            'synced_at' => now(),
        ]);

        $service = app(RegisterMetrics::class);
        $metrics = $service->for($this->makeUserWithRole('procurement_manager'), 'MBL');

        $this->assertNotNull($metrics);
        $this->assertCount(6, $metrics['monthly']);

        $march = collect($metrics['monthly'])->firstWhere('month', '2026-03');
        $this->assertNotNull($march);
        $this->assertSame(0, $march['po_docs']);
        $this->assertSame(0, $march['pr_docs']);
        $this->assertSame('0.00', $march['po_value']);

        $august = collect($metrics['monthly'])->firstWhere('month', '2026-08');
        $this->assertNotNull($august);
        $this->assertSame(1, $august['po_docs']);
    }
}