import { Head } from '@inertiajs/react';
import { Card, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function Index({ requests = { data: [] }, showForm = false, prefill = {} }: { requests?: { data: unknown[] }; showForm?: boolean; prefill?: Record<string, unknown> }) {
    return (
        <AppLayout title="Overbudget">
            <Head title="Overbudget" />
            <Card title="Overbudget Requests">
                <Table rowKey="id" dataSource={requests.data as never[]} columns={[{ title: 'No', dataIndex: 'request_no' }, { title: 'Status', dataIndex: 'status' }]} />
            </Card>
        </AppLayout>
    );
}
