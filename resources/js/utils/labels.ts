const ROLES: Record<string, string> = {
    planner: 'Planner',
    mechanic: 'Mechanic',
    project_manager: 'Project Manager',
    plant_manager: 'Plant Manager',
    finance_director: 'Finance Director',
    operation_director: 'Operation Director',
    president_director: 'President Director',
    buyer: 'Buyer',
    procurement_manager: 'Procurement Manager',
    procurement_admin: 'Procurement Admin',
    logistic_foreman: 'Logistic Foreman',
    logistic_pic: 'Logistic PIC',
    aml_manager: 'AML Manager',
    aml_dept_head: 'AML Dept Head',
    it_manager: 'IT Manager',
};

const STATUSES: Record<string, string> = {
    draft: 'Draft',
    pending_pm: 'Pending Project Manager',
    pending_plant_mgr: 'Pending Plant Manager',
    approved: 'Approved',
    pr_created: 'PR Created',
    po_created: 'PO Created',
    received: 'Received',
    cancelled: 'Cancelled',
    rejected: 'Rejected',
    pending_proc_mgr: 'Pending Procurement Manager',
    forwarded_admin: 'Forwarded to Procurement Admin',
    closed: 'Closed',
    pending_fin_dir: 'Pending Finance Director',
    pending_ops_dir: 'Pending Operation Director',
    pending: 'Pending',
    rfu: 'Ready for Use',
    standby: 'Standby',
    breakdown: 'Breakdown',
};

function titleFromSnakeCase(value: string): string {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export function roleLabel(code: string, roleLabels?: Record<string, string> | null): string {
    if (roleLabels?.[code]) {
        return roleLabels[code];
    }
    if (ROLES[code]) {
        return ROLES[code];
    }

    return titleFromSnakeCase(code);
}

export function statusLabel(code: string, statusLabels?: Record<string, string> | null): string {
    if (statusLabels?.[code]) {
        return statusLabels[code];
    }
    if (STATUSES[code]) {
        return STATUSES[code];
    }

    return titleFromSnakeCase(code);
}
