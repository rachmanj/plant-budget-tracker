import { Head } from '@inertiajs/react';
import { Card, Col, Row, Statistic, Table, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import ProcurementReportToolbar, {
    type DepartmentOption,
    type ProcurementReportFilters,
    type ProjectOption,
} from '@/Pages/Reports/ProcurementReportToolbar';

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

    return (
        <AppLayout title="Approval Turnaround">
            <Head title="Approval Turnaround" />
            <Card title="PR to PO Turnaround">
                <ProcurementReportToolbar
                    reportPath="/reports/approval-turnaround"
                    reportType="approval-turnaround"
                    filters={filters}
                    projects={projects}
                    departments={departments}
                    projectScope={projectScope}
                    canExport={can.export === true}
                />

                <Row gutter={16} style={{ marginBottom: 24 }}>
                    <Col xs={12} md={6}>
                        <Statistic title="Linked POs" value={summary.document_count} />
                    </Col>
                    <Col xs={12} md={6}>
                        <Statistic title="Average days" value={summary.average_days} />
                    </Col>
                    <Col xs={12} md={6}>
                        <Statistic title="Median days" value={summary.median_days} />
                    </Col>
                    <Col xs={12} md={6}>
                        <Statistic title="0–3 days" value={summary.buckets?.['0_3'] ?? 0} />
                    </Col>
                </Row>

                {rows.length === 0 ? (
                    <Typography.Text type="secondary">
                        No linked PR/PO pairs in this period for the selected filters.
                    </Typography.Text>
                ) : (
                    <Table
                        rowKey="department_code"
                        dataSource={rows}
                        pagination={false}
                        columns={[
                            { title: 'Department', dataIndex: 'department_name' },
                            { title: 'Documents', dataIndex: 'document_count' },
                            { title: 'Avg days', dataIndex: 'average_days' },
                            { title: 'Median days', dataIndex: 'median_days' },
                            { title: '0–3', render: (_, row) => row.buckets['0_3'] },
                            { title: '4–7', render: (_, row) => row.buckets['4_7'] },
                            { title: '8–14', render: (_, row) => row.buckets['8_14'] },
                            { title: '>14', render: (_, row) => row.buckets.over_14 },
                        ]}
                    />
                )}
            </Card>
        </AppLayout>
    );
}
