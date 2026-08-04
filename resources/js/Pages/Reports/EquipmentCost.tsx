import { Head } from '@inertiajs/react';
import { Card, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function EquipmentCost({ data = [] }: { data?: unknown[] }) {
    return (
        <AppLayout title="Equipment Cost">
            <Head title="Equipment Cost" />
            <Card title="Equipment Cost Analysis">
                <Table rowKey="equipment_id" dataSource={data as never[]} columns={[
                    { title: 'Equipment', dataIndex: 'equipment_id' },
                    { title: 'Cost/Hour', dataIndex: 'cost_per_hour' },
                ]} />
            </Card>
        </AppLayout>
    );
}
