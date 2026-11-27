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
    rank: number;
    vendor_code: string;
    vendor_name: string;
    document_count: number;
    total_amount: string;
    share_pct: string;
}

interface ReportData {
    summary: { document_count: number; total_amount: string; supplier_count: number };
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

export default function TopSupplier({
    data,
    filters,
    projects,
    departments,
    projectScope,
    can = {},
}: Props) {
    const rows = data?.rows ?? [];
    const summary = data?.summary ?? { document_count: 0, total_amount: '0.00', supplier_count: 0 };
    const subtitle = formatReportPeriodSubtitle(filters.from, filters.to, rows.length);

    return (
        <AppLayout title="Top Suppliers">
            <Head title="Top Suppliers" />
            <ReportPage
                title="Top Suppliers by PO Value"
                subtitle={subtitle || undefined}
                kpis={[
                    { label: 'Suppliers', value: formatDocCount(summary.supplier_count) },
                    { label: 'PO documents', value: formatDocCount(summary.document_count) },
                    {
                        label: 'Total PO value',
                        value: (
                            <Tooltip title={formatIdr(summary.total_amount)}>
                                <span>{formatIdrCompact(summary.total_amount)}</span>
                            </Tooltip>
                        ),
                    },
                ]}
            >
                <ProcurementReportToolbar
                    reportPath="/reports/top-supplier"
                    reportType="top-supplier"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                {rows.length === 0 ? (
                    <Typography.Text type="secondary">
                        No supplier PO activity in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
                        style={{ marginTop: 16 }}
                        rowKey="vendor_code"
                        dataSource={rows}
                        pagination={false}
                        columns={[
                            { title: 'Rank', dataIndex: 'rank', width: 72 },
                            { title: 'Vendor', dataIndex: 'vendor_name' },
                            { title: 'Code', dataIndex: 'vendor_code' },
                            { title: 'PO count', dataIndex: 'document_count' },
                            {
                                title: 'Total value',
                                dataIndex: 'total_amount',
                                render: (value: string) => (
                                    <Tooltip title={formatIdr(value)}>
                                        <span>{formatIdrCompact(value)}</span>
                                    </Tooltip>
                                ),
                            },
                            {
                                title: 'Share',
                                dataIndex: 'share_pct',
                                render: (value: string) => `${value}%`,
                            },
                        ]}
                    />
                )}
            </ReportPage>
        </AppLayout>
    );
}