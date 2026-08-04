import { Head } from '@inertiajs/react';
import { Card, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function BudgetConsumption({ data = [], projectCode, month }: { data?: unknown[]; projectCode?: string; month?: string }) {
    return (
        <AppLayout title="Budget Consumption">
            <Head title="Budget Consumption" />
            <Card title={`Budget Consumption — ${projectCode} ${month}`}>
                <Table rowKey="allocation_id" dataSource={data as never[]} columns={[
                    { title: 'Unit', dataIndex: 'unit_code' },
                    { title: 'Allocated', dataIndex: 'allocated' },
                    { title: 'Committed', dataIndex: 'committed' },
                    { title: 'Actual', dataIndex: 'actual' },
                ]} />
            </Card>
        </AppLayout>
    );
}
