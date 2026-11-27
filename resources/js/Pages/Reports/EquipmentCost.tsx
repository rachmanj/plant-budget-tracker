import { Head } from '@inertiajs/react';
import { Table } from 'antd';
import ReportExportButtons from '@/Components/ReportExportButtons';
import AppLayout from '@/Layouts/AppLayout';
import { ReportPage } from '@/Pages/Reports/reportShell';

interface Props {
    data?: unknown[];
    projectCode?: string;
    month?: string;
    can?: { export?: boolean };
}

export default function EquipmentCost({ data = [], projectCode, month, can = {} }: Props) {
    const rows = Array.isArray(data) ? data : [];
    const subtitle =
        projectCode && month
            ? `${projectCode}, ${month}, ${rows.length} rows`
            : undefined;

    return (
        <AppLayout title="Equipment Cost">
            <Head title="Equipment Cost" />
            <ReportPage title="Equipment Cost Analysis" subtitle={subtitle}>
                <ReportExportButtons
                    reportType="equipment-cost"
                    projectCode={projectCode}
                    month={month}
                    canExport={can.export === true}
                />
                <Table
                    style={{ marginTop: 16 }}
                    rowKey="equipment_id"
                    dataSource={data as never[]}
                    columns={[
                        { title: 'Equipment', dataIndex: 'equipment_id' },
                        { title: 'Cost/Hour', dataIndex: 'cost_per_hour' },
                    ]}
                />
            </ReportPage>
        </AppLayout>
    );
}