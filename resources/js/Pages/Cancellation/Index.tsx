import { Head } from '@inertiajs/react';
import { Card, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function Index({ requests }: { requests: { data: unknown[] } }) {
    return (
        <AppLayout title="Cancellation">
            <Head title="Cancellation" />
            <Card title="Cancellation Requests">
                <Table rowKey="id" dataSource={requests.data as never[]} columns={[{ title: 'Plant Request', dataIndex: ['plant_request_id'] }, { title: 'Status', dataIndex: 'status' }]} />
            </Card>
        </AppLayout>
    );
}
