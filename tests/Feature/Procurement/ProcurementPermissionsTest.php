<?php

namespace Tests\Feature\Procurement;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcurementPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_procurement_view_permission_is_assigned_to_expected_roles(): void
    {
        $expected = [
            'procurement_admin',
            'procurement_manager',
            'buyer',
            'it_manager',
            'plant_manager',
            'project_manager',
            'finance_director',
            'president_director',
            'operation_director',
        ];

        foreach ($expected as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $this->assertTrue(
                $role->hasPermissionTo('procurement.view'),
                "Expected {$roleName} to have procurement.view"
            );
        }
    }

    public function test_procurement_sync_permission_is_limited_to_admin_roles(): void
    {
        $allowed = ['procurement_admin', 'procurement_manager', 'it_manager'];
        $denied = ['buyer', 'plant_manager', 'project_manager', 'finance_director'];

        foreach ($allowed as $roleName) {
            $this->assertTrue(
                Role::findByName($roleName, 'web')->hasPermissionTo('procurement.sync')
            );
        }

        foreach ($denied as $roleName) {
            $this->assertFalse(
                Role::findByName($roleName, 'web')->hasPermissionTo('procurement.sync')
            );
        }
    }
}
