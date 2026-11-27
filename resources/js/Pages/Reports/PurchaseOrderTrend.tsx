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
    period: string;
    period_label: string;
    document_count: number;
    total_amount: string;
    pmb_count: number;
    sap_count: number;
    pmb_amount: string;
    sap_amount: string;
}

interface ReportData {
    summary: { document_count: number; total_amount: string; pmb_count: number; sap_count: number };
    rows: Row[];
    granularity: string;
}

interface Props {
    data: ReportData;
    filters: ProcurementReportFilters;
    projects: ProjectOption[];
    departments: DepartmentOption[];
    projectScope: string | null;
    can?: { export?: boolean };
}

export default function PurchaseOrderTrend({
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
        total_amount: '0.00',
        pmb_count: 0,
        sap_count: 0,
    };

    const subtitle = formatReportPeriodSubtitle(filters.from, filters.to, rows.length);

    return (
        <AppLayout title="Purchase Order Trend">
            <Head title="Purchase Order Trend" />
            <ReportPage
                title="Purchase Order Trend"
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
                    { label: 'From PMB', value: formatDocCount(summary.pmb_count) },
                    { label: 'From SAP', value: formatDocCount(summary.sap_count) },
                ]}
            >
                <ProcurementReportToolbar
                    reportPath="/reports/purchase-order-trend"
                    reportType="purchase-order-trend"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                {rows.length === 0 ? (
                    <Typography.Text type="secondary">
                        No purchase orders in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
                        style={{ marginTop: 16 }}
                        rowKey="period"
                        dataSource={rows}
                        pagination={false}
                        columns={[
                            {
                                title: data?.granularity === 'day' ? 'Day' : 'Month',
                                dataIndex: 'period_label',
                            },
                            { title: 'PO count', dataIndex: 'document_count' },
                            {
                                title: 'Total',
                                dataIndex: 'total_amount',
                                render: (value: string) => (
                                    <Tooltip title={formatIdr(value)}>
                                        <span>{formatIdrCompact(value)}</span>
                                    </Tooltip>
                                ),
                            },
                            { title: 'PMB POs', dataIndex: 'pmb_count' },
                            { title: 'SAP POs', dataIndex: 'sap_count' },
                            {
                                title: 'PMB value',
                                dataIndex: 'pmb_amount',
                                render: (value: string) => formatIdrCompact(value),
                            },
                            {
                                title: 'SAP value',
                                dataIndex: 'sap_amount',
                                render: (value: string) => formatIdrCompact(value),
                            },
                        ]}
                    />
                )}
            </ReportPage>
        </AppLayout>
    );
}