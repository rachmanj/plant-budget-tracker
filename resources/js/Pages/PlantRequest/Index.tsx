import { Head, Link, router } from '@inertiajs/react';
import { Button, Card, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import LifecycleStepper from '@/Components/LifecycleStepper';

interface PlantRequestRow {
    id: number;
    request_no: string;
    status: string;
    unit_code_cache: string;
    estimated_total: string;
}

interface Props {
    requests: { data: PlantRequestRow[] };
    filters: { status?: string };
}

export default function Index({ requests }: Props) {
    const columns = [
        { title: 'No. Request', dataIndex: 'request_no', key: 'request_no' },
        { title: 'Unit', dataIndex: 'unit_code_cache', key: 'unit_code_cache' },
        { title: 'Total Est.', dataIndex: 'estimated_total', key: 'estimated_total' },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (status: string) => <Tag>{status}</Tag>,
        },
        {
            title: 'Lifecycle',
            key: 'lifecycle',
            render: (_: unknown, row: PlantRequestRow) => <LifecycleStepper status={row.status} />,
        },
        {
            title: 'Action',
            key: 'action',
            render: (_: unknown, row: PlantRequestRow) => (
                <Link href={`/plant-requests/${row.id}`}>Detail</Link>
            ),
        },
    ];

    return (
        <AppLayout title="Plant Requests">
            <Head title="Plant Requests" />
            <Card
                title="Plant Requests"
                extra={
                    <Link href="/plant-requests/create">
                        <Button type="primary">Buat Request</Button>
                    </Link>
                }
            >
                <Table rowKey="id" columns={columns} dataSource={requests.data} pagination={false} />
            </Card>
        </AppLayout>
    );
}
