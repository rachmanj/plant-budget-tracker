<?php

namespace App\Support;

class RoleLabels
{
    /** @var array<string, string> */
    private const ROLES = [
        'planner' => 'Planner',
        'mechanic' => 'Mechanic',
        'project_manager' => 'Project Manager',
        'plant_manager' => 'Plant Manager',
        'finance_director' => 'Finance Director',
        'operation_director' => 'Operation Director',
        'president_director' => 'President Director',
        'buyer' => 'Buyer',
        'procurement_manager' => 'Procurement Manager',
        'procurement_admin' => 'Procurement Admin',
        'logistic_foreman' => 'Logistic Foreman',
        'logistic_pic' => 'Logistic PIC',
        'aml_manager' => 'AML Manager',
        'aml_dept_head' => 'AML Dept Head',
        'it_manager' => 'IT Manager',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'draft' => 'Draft',
        'pending_pm' => 'Pending Project Manager',
        'pending_plant_mgr' => 'Pending Plant Manager',
        'approved' => 'Approved',
        'pr_created' => 'PR Created',
        'po_created' => 'PO Created',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'pending_proc_mgr' => 'Pending Procurement Manager',
        'forwarded_admin' => 'Forwarded to Procurement Admin',
        'closed' => 'Closed',
        'pending_fin_dir' => 'Pending Finance Director',
        'pending_ops_dir' => 'Pending Operation Director',
        'pending' => 'Pending',
        'rfu' => 'Ready for Use',
        'standby' => 'Standby',
        'breakdown' => 'Breakdown',
    ];

    public static function label(string $role): string
    {
        if (isset(self::ROLES[$role])) {
            return self::ROLES[$role];
        }

        return self::titleFromSnakeCase($role);
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::ROLES;
    }

    public static function status(string $status): string
    {
        if (isset(self::STATUSES[$status])) {
            return self::STATUSES[$status];
        }

        return self::titleFromSnakeCase($status);
    }

    private static function titleFromSnakeCase(string $value): string
    {
        return collect(explode('_', $value))
            ->map(fn (string $word) => ucfirst($word))
            ->implode(' ');
    }
}
