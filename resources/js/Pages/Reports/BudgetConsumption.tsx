import { Head } from '@inertiajs/react';
import { Card, Descriptions, Table, Typography } from 'antd';
import ReportExportButtons from '@/Components/ReportExportButtons';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';

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
        <AppLayout title="Konsumsi Anggaran">
            <Head title="Konsumsi Anggaran" />
            <Card title={`Konsumsi Anggaran — ${projectCode ?? ''} ${month ?? ''}`}>
                <ReportExportButtons
                    reportType="budget-consumption"
                    projectCode={projectCode}
                    month={month}
                    canExport={can.export === true}
                />

                {summary ? (
                    <>
                        <Descriptions bordered size="small" column={3} style={{ marginBottom: 24 }}>
                            <Descriptions.Item label="Pagu proyek">{formatIdr(summary.pagu)}</Descriptions.Item>
                            <Descriptions.Item label="Komitmen">{formatIdr(summary.committed)}</Descriptions.Item>
                            <Descriptions.Item label="Aktual">{formatIdr(summary.actual)}</Descriptions.Item>
                            <Descriptions.Item label="Sisa">{formatIdr(summary.remaining)}</Descriptions.Item>
                            <Descriptions.Item label="Terpakai">{summary.utilization_pct}%</Descriptions.Item>
                        </Descriptions>

                        <Typography.Title level={5}>Rincian per unit (dari permintaan)</Typography.Title>
                        <Table
                            rowKey="equipment_id"
                            dataSource={units}
                            pagination={false}
                            columns={[
                                { title: 'Unit', dataIndex: 'unit_code' },
                                { title: 'Jumlah permintaan', dataIndex: 'request_count' },
                                {
                                    title: 'Total nilai permintaan',
                                    dataIndex: 'total_estimated',
                                    render: (value: string) => formatIdr(value),
                                },
                                { title: 'Status terakhir', dataIndex: 'last_status' },
                            ]}
                        />
                    </>
                ) : (
                    <Typography.Text type="secondary">
                        Tidak ada data pagu untuk periode ini.
                    </Typography.Text>
                )}
            </Card>
        </AppLayout>
    );
}
