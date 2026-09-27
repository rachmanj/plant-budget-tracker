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

    return (
        <AppLayout title="Purchase Request Status">
            <Head title="Purchase Request Status" />
            <Card title="Purchase Request Status">
                <ProcurementReportToolbar
                    reportPath="/reports/purchase-request-status"
                    reportType="purchase-request-status"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                <Row gutter={16} style={{ marginBottom: 24 }}>
                    <Col xs={24} sm={12} md={8}>
                        <Statistic title="Documents" value={summary.document_count} />
                    </Col>
                    <Col xs={24} sm={12} md={8}>
                        <Statistic
                            title="Total value"
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
                        No purchase requests in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
                        rowKey={(row) =>
                            `${row.department_code}-${row.pr_status}-${row.closed_status}`
                        }
                        dataSource={rows}
                        pagination={false}
                        columns={[
                            { title: 'Department', dataIndex: 'department_name' },
                            { title: 'PR status', dataIndex: 'pr_status' },
                            { title: 'Closed', dataIndex: 'closed_status', render: (v) => v || '—' },
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
            </Card>
        </AppLayout>
    );
}
