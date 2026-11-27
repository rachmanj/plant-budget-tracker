import { Head } from '@inertiajs/react';
import { Table, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import { formatDocCount } from '@/Pages/Procurement/registerIndexUi';
import ProcurementReportToolbar, {
    type DepartmentOption,
    type ProcurementReportFilters,
    type ProjectOption,
} from '@/Pages/Reports/ProcurementReportToolbar';
import { formatReportPeriodSubtitle, ReportPage } from '@/Pages/Reports/reportShell';

interface Bucket {
    '0_3': number;
    '4_7': number;
    '8_14': number;
    over_14: number;
}

interface Row {
    department_code: string;
    department_name: string;
    document_count: number;
    average_days: string;
    median_days: string;
    buckets: Bucket;
}

interface ReportData {
    summary: {
        document_count: number;
        average_days: string;
        median_days: string;
        buckets: Bucket;
    };
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

export default function ApprovalTurnaround({
    data,
    filters,
    projects,
    departments,
    projectScope,
    can = {},
}: Props) {
    const rows = data?.rows ?? [];
    const summary = data?.summary ?? {
        document_count: 0,
        average_days: '0.00',
        median_days: '0.00',
        buckets: { '0_3': 0, '4_7': 0, '8_14': 0, over_14: 0 },
    };
    const subtitle = formatReportPeriodSubtitle(filters.from, filters.to, rows.length);

    return (
        <AppLayout title="Approval Turnaround">
            <Head title="Approval Turnaround" />
            <ReportPage
                title="PR to PO Turnaround"
                subtitle={subtitle || undefined}
                kpis={[
                    { label: 'Linked POs', value: formatDocCount(summary.document_count) },
                    { label: 'Average days', value: summary.average_days },
                    { label: 'Median days', value: summary.median_days },
                    { label: '0-3 days', value: formatDocCount(summary.buckets?.['0_3'] ?? 0) },
                ]}
            >
                <ProcurementReportToolbar
                    reportPath="/reports/approval-turnaround"
                    reportType="approval-turnaround"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                {rows.length === 0 ? (
                    <Typography.Text type="secondary">
                        No linked PR/PO pairs in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
                        style={{ marginTop: 16 }}
                        rowKey="department_code"
                        dataSource={rows}
                        pagination={false}
                        columns={[
                            { title: 'Department', dataIndex: 'department_name' },
                            { title: 'Documents', dataIndex: 'document_count' },
                            { title: 'Avg days', dataIndex: 'average_days' },
                            { title: 'Median days', dataIndex: 'median_days' },
                            { title: '0-3', render: (_, row) => row.buckets['0_3'] },
                            { title: '4-7', render: (_, row) => row.buckets['4_7'] },
                            { title: '8-14', render: (_, row) => row.buckets['8_14'] },
                            { title: '>14', render: (_, row) => row.buckets.over_14 },
                        ]}
                    />
                )}
            </ReportPage>
        </AppLayout>
    );
}