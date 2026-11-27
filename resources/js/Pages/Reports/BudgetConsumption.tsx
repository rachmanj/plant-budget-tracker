import { Head } from '@inertiajs/react';
import { Table, Typography } from 'antd';
import ReportExportButtons from '@/Components/ReportExportButtons';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';
import { ReportPage } from '@/Pages/Reports/reportShell';
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

    const subtitle =
        projectCode && month ? `${projectCode}, ${month}, ${units.length} units` : undefined;

    const kpis = summary
        ? [
              { label: 'Project ceiling', value: formatIdr(summary.pagu) },
              { label: 'Committed', value: formatIdr(summary.committed) },
              { label: 'Actual', value: formatIdr(summary.actual) },
              { label: 'Remaining', value: formatIdr(summary.remaining) },
              { label: 'Used', value: `${summary.utilization_pct}%` },
          ]
        : undefined;

    return (
        <AppLayout title="Budget Consumption">
            <Head title="Budget Consumption" />
            <ReportPage title="Budget Consumption" subtitle={subtitle} kpis={kpis}>
                <ReportExportButtons
                    reportType="budget-consumption"
                    projectCode={projectCode}
                    month={month}
                    canExport={can.export === true}
                />

                {summary ? (
                    <>
                        <Typography.Title level={5} style={{ marginTop: 16 }}>
                            Detail by unit (from requests)
                        </Typography.Title>
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
                    <Typography.Text type="secondary">No budget data for this period.</Typography.Text>
                )}
            </ReportPage>
        </AppLayout>
    );
}