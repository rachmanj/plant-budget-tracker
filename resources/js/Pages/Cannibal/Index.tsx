import { Head, Link } from '@inertiajs/react';
import { Button, Card, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function Index({ requests }: { requests: { data: unknown[] } }) {
    return (
        <AppLayout title="Cannibal Requests">
            <Head title="Cannibal" />
            <Card title="Cannibal Requests" extra={<Link href="/cannibal-requests/create"><Button type="primary">Buat Request</Button></Link>}>
                <Table rowKey="id" dataSource={requests.data as never[]} columns={[{ title: 'No', dataIndex: 'request_no' }, { title: 'Status', dataIndex: 'status' }]} />
            </Card>
        </AppLayout>
    );
}
