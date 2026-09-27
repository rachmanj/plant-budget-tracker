import { Head } from '@inertiajs/react';
import { Card, Col, Row, Statistic, Table, Tooltip, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';
import ProcurementReportToolbar, {
    type DepartmentOption,
    type ProcurementReportFilters,
    type ProjectOption,
} from '@/Pages/Reports/ProcurementReportToolbar';

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

    return (
        <AppLayout title="Purchase Order Trend">
            <Head title="Purchase Order Trend" />
            <Card title="Purchase Order Trend">
                <ProcurementReportToolbar
                    reportPath="/reports/purchase-order-trend"
                    reportType="purchase-order-trend"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                <Row gutter={16} style={{ marginBottom: 24 }}>
                    <Col xs={12} md={6}>
                        <Statistic title="PO documents" value={summary.document_count} />
                    </Col>
                    <Col xs={12} md={6}>
                        <Statistic
                            title="Total PO value"
                            valueRender={() => (
                                <Tooltip title={formatIdr(summary.total_amount)}>
                                    <span>{formatIdrCompact(summary.total_amount)}</span>
                                </Tooltip>
                            )}
                        />
                    </Col>
                    <Col xs={12} md={6}>
                        <Statistic title="PMB origin" value={summary.pmb_count} />
                    </Col>
                    <Col xs={12} md={6}>
                        <Statistic title="SAP origin" value={summary.sap_count} />
                    </Col>
                </Row>

                {rows.length === 0 ? (
                    <Typography.Text type="secondary">
                        No purchase orders in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
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
            </Card>
        </AppLayout>
    );
}
