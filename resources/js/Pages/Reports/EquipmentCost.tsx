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

export default function EquipmentCost({ data = [], projectCode, month, can = {} }: Props) {
    return (
        <AppLayout title="Biaya Peralatan">
            <Head title="Biaya Peralatan" />
            <Card title={`Analisis Biaya Peralatan — ${projectCode ?? ''} ${month ?? ''}`}>
                <ReportExportButtons
                    reportType="equipment-cost"
                    projectCode={projectCode}
                    month={month}
                    canExport={can.export === true}
                />
                <Table
                    rowKey="equipment_id"
                    dataSource={data as never[]}
                    columns={[
                        { title: 'Equipment', dataIndex: 'equipment_id' },
                        { title: 'Cost/Hour', dataIndex: 'cost_per_hour' },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
