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
    pr_status: string;
    closed_status: string;
    document_count: number;
    total_amount: string;
}

interface ReportData {
    summary: { document_count: number; total_amount: string };
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

export default function PurchaseRequestStatus({
    data,
    filters,
    projects,
    departments,
    projectScope,
    can = {},
}: Props) {
    const rows = data?.rows ?? [];
    const summary = data?.summary ?? { document_count: 0, total_amount: '0.00' };
    const subtitle = formatReportPeriodSubtitle(filters.from, filters.to, rows.length);

    return (
        <AppLayout title="Purchase Request Status">
            <Head title="Purchase Request Status" />
            <ReportPage
                title="Purchase Request Status"
                subtitle={subtitle || undefined}
                kpis={[
                    { label: 'Documents', value: formatDocCount(summary.document_count) },
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
                    reportPath="/reports/purchase-request-status"
                    reportType="purchase-request-status"
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
                        rowKey={(row) =>
                            `${row.department_code}-${row.pr_status}-${row.closed_status}`
                        }
                        dataSource={rows}
                        pagination={false}
                        columns={[
                            { title: 'Department', dataIndex: 'department_name' },
                            { title: 'PR status', dataIndex: 'pr_status' },
                            { title: 'Closed', dataIndex: 'closed_status', render: (v) => v || '-' },
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