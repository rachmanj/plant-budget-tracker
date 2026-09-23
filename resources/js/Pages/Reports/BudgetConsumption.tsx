import { Head } from '@inertiajs/react';
import { Card, Descriptions, Table, Typography } from 'antd';
import ReportExportButtons from '@/Components/ReportExportButtons';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';
import { statusLabel } from '@/utils/labels';

interface ProjectSummary {
    project_code: string;
    pagu: string;
    committed: string;
    actual: string;
    remaining: string;
    utilization_pct: string;
}

interface UnitRow {
    equipment_id: number;
    unit_code: string;
    request_count: number;
    total_estimated: string;
    last_status: string;
}

interface ReportData {
    summary: ProjectSummary | null;
    units: UnitRow[];
}

interface Props {
    data?: ReportData;
    projectCode?: string;
    month?: string;
    can?: { export?: boolean };
}

export default function BudgetConsumption({ data, projectCode, month, can = {} }: Props) {
    const summary = data?.summary ?? null;
    const units = data?.units ?? [];

    return (
        <AppLayout title="Budget Consumption">
            <Head title="Budget Consumption" />
            <Card title={`Budget Consumption — ${projectCode ?? ''} ${month ?? ''}`}>
                <ReportExportButtons
                    reportType="budget-consumption"
                    projectCode={projectCode}
                    month={month}
                    canExport={can.export === true}
                />

                {summary ? (
                    <>
                        <Descriptions bordered size="small" column={3} style={{ marginBottom: 24 }}>
                            <Descriptions.Item label="Project ceiling">{formatIdr(summary.pagu)}</Descriptions.Item>
                            <Descriptions.Item label="Committed">{formatIdr(summary.committed)}</Descriptions.Item>
                            <Descriptions.Item label="Actual">{formatIdr(summary.actual)}</Descriptions.Item>
                            <Descriptions.Item label="Remaining">{formatIdr(summary.remaining)}</Descriptions.Item>
                            <Descriptions.Item label="Used">{summary.utilization_pct}%</Descriptions.Item>
                        </Descriptions>

                        <Typography.Title level={5}>Detail by unit (from requests)</Typography.Title>
                        <Table
                            rowKey="equipment_id"
                            dataSource={units}
                            pagination={false}
                            columns={[
                                { title: 'Unit', dataIndex: 'unit_code' },
                                { title: 'Request count', dataIndex: 'request_count' },
                                {
                                    title: 'Total request value',
                                    dataIndex: 'total_estimated',
                                    render: (value: string) => formatIdr(value),
                                },
                                {
                                    title: 'Last status',
                                    dataIndex: 'last_status',
                                    render: (value: string) => statusLabel(value),
                                },
                            ]}
                        />
                    </>
                ) : (
                    <Typography.Text type="secondary">
                        No budget data for this period.
                    </Typography.Text>
                )}
            </Card>
        </AppLayout>
    );
}
