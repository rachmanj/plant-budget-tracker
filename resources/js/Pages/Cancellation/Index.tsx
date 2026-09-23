import { Head, router } from '@inertiajs/react';
import { Button, Card, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';
import { statusLabel } from '@/utils/labels';

interface CancellationRow {
    id: number;
    initiated_by: string;
    po_stage: string | null;
    budget_reversal_amount: string;
    status: string;
    plant_request?: { request_no: string };
    can: { agree: boolean };
}

interface Props {
    requests: { data: CancellationRow[] };
}

export default function Index({ requests }: Props) {
    const columns: ColumnsType<CancellationRow> = [
        {
            title: 'Plant Request',
            key: 'request_no',
            render: (_, row) => row.plant_request?.request_no ?? '—',
        },
        { title: 'Initiated By', dataIndex: 'initiated_by' },
        { title: 'PO Stage', dataIndex: 'po_stage' },
        {
            title: 'Reversal Amount',
            dataIndex: 'budget_reversal_amount',
            render: (value: string) => formatIdr(value),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            render: (status: string) => <Tag>{statusLabel(status)}</Tag>,
        },
        {
            title: 'Actions',
            key: 'actions',
            render: (_, row) =>
                row.can.agree ? (
                    <Button type="primary" size="small" onClick={() => router.post(`/cancellation-requests/${row.id}/agree`)}>
                        Agree
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout title="Cancellation">
            <Head title="Cancellation" />
            <Card title="Cancellation Requests">
                <Table rowKey="id" dataSource={requests.data} columns={columns} />
            </Card>
        </AppLayout>
    );
}
