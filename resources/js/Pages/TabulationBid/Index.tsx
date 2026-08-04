import { Head } from '@inertiajs/react';
import { Card, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    bids: { data: Array<{ id: number; bid_no: string; status: string; sap_pr_id: string }> };
}

export default function Index({ bids }: Props) {
    return (
        <AppLayout title="Tabulation Bids">
            <Head title="Tabulation Bids" />
            <Card title="Tabulation Bids">
                <Table
                    rowKey="id"
                    dataSource={bids.data}
                    columns={[
                        { title: 'Bid No', dataIndex: 'bid_no' },
                        { title: 'SAP PR', dataIndex: 'sap_pr_id' },
                        { title: 'Status', dataIndex: 'status', render: (s: string) => <Tag>{s}</Tag> },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
