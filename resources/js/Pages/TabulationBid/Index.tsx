import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Card, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import { statusLabel } from '@/utils/labels';

interface PageProps {
    auth: { can: string[] };
}

interface Props {
    bids: { data: Array<{ id: number; bid_no: string; status: string; sap_pr_id: string }> };
}

export default function Index({ bids }: Props) {
    const { auth } = usePage<PageProps>().props;
    const can = auth.can ?? [];

    return (
        <AppLayout title="Tabulation Bids">
            <Head title="Tabulation Bids" />
            <Card
                title="Tabulation Bids"
                extra={
                    can.includes('tabulation_bid.create') ? (
                        <Link href="/tabulation-bids/create">
                            <Button type="primary">Create Bid</Button>
                        </Link>
                    ) : null
                }
            >
                <Table
                    rowKey="id"
                    dataSource={bids.data}
                    columns={[
                        { title: 'Bid No', dataIndex: 'bid_no' },
                        { title: 'SAP PR', dataIndex: 'sap_pr_id' },
                        { title: 'Status', dataIndex: 'status', render: (s: string) => <Tag>{statusLabel(s)}</Tag> },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
