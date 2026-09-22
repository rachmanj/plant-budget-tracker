import { Head } from '@inertiajs/react';
import { Card, Table } from 'antd';
import ReportExportButtons from '@/Components/ReportExportButtons';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    data?: unknown[];
    projectCode?: string;
    month?: string;
    can?: { export?: boolean };
}

export default function BudgetConsumption({ data = [], projectCode, month, can = {} }: Props) {
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
                <Table
                    rowKey="allocation_id"
                    dataSource={data as never[]}
                    columns={[
                        { title: 'Unit', dataIndex: 'unit_code' },
                        { title: 'Allocated', dataIndex: 'allocated' },
                        { title: 'Committed', dataIndex: 'committed' },
                        { title: 'Actual', dataIndex: 'actual' },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
