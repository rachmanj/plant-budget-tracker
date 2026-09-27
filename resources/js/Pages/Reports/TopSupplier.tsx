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

    return (
        <AppLayout title="Top Suppliers">
            <Head title="Top Suppliers" />
            <Card title="Top Suppliers by PO Value">
                <ProcurementReportToolbar
                    reportPath="/reports/top-supplier"
                    reportType="top-supplier"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                <Row gutter={16} style={{ marginBottom: 24 }}>
                    <Col xs={12} md={8}>
                        <Statistic title="Suppliers" value={summary.supplier_count} />
                    </Col>
                    <Col xs={12} md={8}>
                        <Statistic title="PO documents" value={summary.document_count} />
                    </Col>
                    <Col xs={24} md={8}>
                        <Statistic
                            title="Total PO value"
                            valueRender={() => (
                                <Tooltip title={formatIdr(summary.total_amount)}>
                                    <span>{formatIdrCompact(summary.total_amount)}</span>
                                </Tooltip>
                            )}
                        />
                    </Col>
                </Row>

                {rows.length === 0 ? (
                    <Typography.Text type="secondary">
                        No supplier PO activity in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
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
            </Card>
        </AppLayout>
    );
}
