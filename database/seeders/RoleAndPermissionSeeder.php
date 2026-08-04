<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'budget.view',
            'budget.set',
            'plant_request.create',
            'plant_request.approve.pm',
            'plant_request.approve.plant_mgr',
            'dmbd.update',
            'dmbd.view',
            'logistic.stock_check',
            'tabulation_bid.create',
            'tabulation_bid.review',
            'po.create',
            'po.approve',
            'overbudget.approve.fin_dir',
            'overbudget.approve.ops_dir',
            'cancellation.plant',
            'cancellation.procurement',
            'interchange.manage',
            'grpo.verify',
            'component.maintain',
            'component.view',
            'cannibal.create',
            'cannibal.approve.1',
            'cannibal.approve.2',
            'cannibal.approve.3',
            'cannibal.approve.4',
            'project.setup',
            'user.manage',
            'reports.view',
            'reports.export',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'planner',
            'mechanic',
            'project_manager',
            'plant_manager',
            'buyer',
            'procurement_manager',
            'procurement_admin',
            'logistic_foreman',
            'logistic_pic',
            'finance_director',
            'operation_director',
            'president_director',
            'it_manager',
            'aml_manager',
            'aml_dept_head',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $matrix = [
            'planner' => ['budget.view', 'plant_request.create', 'dmbd.update', 'dmbd.view', 'cancellation.plant', 'cannibal.create', 'component.view', 'reports.view'],
            'mechanic' => ['budget.view', 'plant_request.create', 'dmbd.view', 'cannibal.create', 'reports.view'],
            'project_manager' => ['budget.view', 'plant_request.approve.pm', 'dmbd.view', 'cancellation.plant', 'reports.view'],
            'plant_manager' => ['budget.view', 'plant_request.approve.plant_mgr', 'dmbd.view', 'cancellation.plant', 'cannibal.approve.1', 'component.view', 'reports.view', 'reports.export'],
            'buyer' => ['budget.view', 'tabulation_bid.create', 'cancellation.procurement', 'interchange.manage', 'reports.view'],
            'procurement_manager' => ['budget.view', 'tabulation_bid.review', 'cancellation.procurement', 'interchange.manage', 'reports.view', 'reports.export'],
            'procurement_admin' => ['budget.view', 'po.create', 'cancellation.procurement', 'interchange.manage', 'reports.view'],
            'logistic_foreman' => ['budget.view', 'logistic.stock_check', 'dmbd.view', 'grpo.verify', 'reports.view'],
            'logistic_pic' => ['budget.view', 'logistic.stock_check', 'dmbd.view', 'grpo.verify', 'reports.view'],
            'finance_director' => ['budget.view', 'budget.set', 'overbudget.approve.fin_dir', 'grpo.verify', 'reports.view', 'reports.export'],
            'operation_director' => ['budget.view', 'overbudget.approve.ops_dir', 'dmbd.view', 'component.view', 'cannibal.approve.3', 'reports.view', 'reports.export'],
            'president_director' => ['budget.view', 'po.approve', 'dmbd.view', 'component.view', 'cannibal.approve.4', 'reports.view', 'reports.export'],
            'it_manager' => ['budget.view', 'project.setup', 'user.manage', 'dmbd.view', 'reports.view'],
            'aml_manager' => ['budget.view', 'component.maintain', 'component.view', 'cannibal.approve.2', 'reports.view', 'reports.export'],
            'aml_dept_head' => ['budget.view', 'component.maintain', 'component.view', 'reports.view'],
        ];

        foreach ($matrix as $roleName => $rolePermissions) {
            Role::findByName($roleName, 'web')->syncPermissions($rolePermissions);
        }
    }
}
