import { Head, Link, usePage } from '@inertiajs/react';
import { Button, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import { AppPage } from '@/Components/AppPage';
import { RegisterTableCard, StatusPill } from '@/Pages/Procurement/registerIndexUi';
import { statusLabel } from '@/utils/labels';

interface PageProps {
    auth: { can: string[] };
}

interface BidRow {
    id: number;
    bid_no: string;
    status: string;
    sap_pr_id: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface Props {
    bids: Paginator<BidRow>;
}

function bidStatusTone(status: string): 'blue' | 'green' | 'orange' {
    const normalized = status.trim().toLowerCase();

    if (normalized === 'po_sent' || normalized === 'po_approved' || normalized === 'closed') {
        return 'green';
    }

    if (normalized === 'draft' || normalized === 'pending_proc_mgr' || normalized === 'forwarded_admin') {
        return 'blue';
    }

    return 'orange';
}

export default function Index({ bids }: Props) {
    const { auth } = usePage<PageProps>().props;
    const can = auth.can ?? [];
    const total = bids.total ?? bids.data.length;

    return (
        <AppLayout title="Tabulation Bids">
            <Head title="Tabulation Bids" />
            <AppPage
                title="Tabulation Bids"
                actions={
                    can.includes('tabulation_bid.create') ? (
                        <Link href="/tabulation-bids/create">
                            <Button type="primary" style={{ minHeight: 44 }}>
                                Create Bid
                            </Button>
                        </Link>
                    ) : null
                }
            >
                <RegisterTableCard showing={bids.data.length} total={total}>
                    <Table<BidRow>
                        rowKey="id"
                        dataSource={bids.data}
                        scroll={{ x: 'max-content' }}
                        columns={[
                            { title: 'Bid No', dataIndex: 'bid_no' },
                            { title: 'SAP PR', dataIndex: 'sap_pr_id' },
                            {
                                title: 'Status',
                                dataIndex: 'status',
                                render: (status: string) => (
                                    <StatusPill tone={bidStatusTone(status)}>{statusLabel(status)}</StatusPill>
                                ),
                            },
                        ]}
                    />
                </RegisterTableCard>
            </AppPage>
        </AppLayout>
    );
}