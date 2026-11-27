import { Head } from '@inertiajs/react';
import { Table, Tooltip, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import { formatDocCount } from '@/Pages/Procurement/registerIndexUi';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';
import ProcurementReportToolbar, {
    type DepartmentOption,
    type ProcurementReportFilters,
    type ProjectOption,
} from '@/Pages/Reports/ProcurementReportToolbar';
import { formatReportPeriodSubtitle, ReportPage } from '@/Pages/Reports/reportShell';

interface Row {
    department_code: string;
    department_name: string;
    project_code: string;
    document_count: number;
    total_amount: string;
}

interface ReportData {
    summary: { document_count: number; total_amount: string; department_count: number };
    rows: Row[];
}

interface Props {
    data: ReportData;
    filters: ProcurementReportFilters;
    projects: ProjectOption[];
    departments: DepartmentOption[];
    projectScope: string | null;
    can?: { export?: boolean };
}

export default function PurchaseRequestByDepartment({
    data,
    filters,
    projects,
    departments,
    projectScope,
    can = {},
}: Props) {
    const rows = data?.rows ?? [];
    const summary = data?.summary ?? { document_count: 0, total_amount: '0.00', department_count: 0 };
    const subtitle = formatReportPeriodSubtitle(filters.from, filters.to, rows.length);

    return (
        <AppLayout title="PR by Department">
            <Head title="PR by Department" />
            <ReportPage
                title="Purchase Requests by Department"
                subtitle={subtitle || undefined}
                kpis={[
                    { label: 'Documents', value: formatDocCount(summary.document_count) },
                    { label: 'Departments', value: formatDocCount(summary.department_count) },
                    {
                        label: 'Total value',
                        value: (
                            <Tooltip title={formatIdr(summary.total_amount)}>
                                <span>{formatIdrCompact(summary.total_amount)}</span>
                            </Tooltip>
                        ),
                    },
                ]}
            >
                <ProcurementReportToolbar
                    reportPath="/reports/purchase-request-by-department"
                    reportType="purchase-request-by-department"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                {rows.length === 0 ? (
                    <Typography.Text type="secondary">
                        No purchase requests in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
                        style={{ marginTop: 16 }}
                        rowKey={(row) => `${row.department_code}-${row.project_code}`}
                        dataSource={rows}
                        pagination={false}
                        columns={[
                            { title: 'Department', dataIndex: 'department_name' },
                            { title: 'Project', dataIndex: 'project_code' },
                            { title: 'Count', dataIndex: 'document_count' },
                            {
                                title: 'Total',
                                dataIndex: 'total_amount',
                                render: (value: string) => (
                                    <Tooltip title={formatIdr(value)}>
                                        <span>{formatIdrCompact(value)}</span>
                                    </Tooltip>
                                ),
                            },
                        ]}
                    />
                )}
            </ReportPage>
        </AppLayout>
    );
}