import { Head, router } from '@inertiajs/react';
import { Button, Card, Descriptions, Modal, Space, Tag, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import VendorComparisonTable from '@/Components/VendorComparisonTable';
import { formatIdr } from '@/hooks/useCurrency';

interface Props {
    bid: {
        id: number;
        bid_no: string;
        status: string;
        sap_po_id?: string | null;
        vendors: Array<{
            id: number;
            vendor_code: string;
            vendor_name: string;
            price: string;
            rank: number;
            stock_availability: string;
        }>;
        award?: {
            tabulation_bid_vendor_id: number;
            vendor?: { vendor_name: string; price: string };
        };
    };
    can: {
        review: boolean;
        award: boolean;
        createPo: boolean;
    };
}

export default function Review({ bid, can = { review: false, award: false, createPo: false } }: Props) {
    const lowestVendor = [...bid.vendors].sort((a, b) => a.rank - b.rank)[0] ?? bid.vendors[0];

    const confirmCreatePo = () => {
        Modal.confirm({
            title: 'Create Purchase Order in SAP?',
            content:
                'This will create a real Purchase Order in SAP B1 via the sync queue. Confirm the winning vendor is correct.',
            okText: 'Create PO',
            cancelText: 'Cancel',
            onOk: () => router.post(`/tabulation-bids/${bid.id}/create-po`),
        });
    };

    return (
        <AppLayout title={`Review ${bid.bid_no}`}>
            <Head title={bid.bid_no} />
            <Card title={`Tabulation Bid ${bid.bid_no}`}>
                <VendorComparisonTable vendors={bid.vendors} />
                {bid.award?.vendor && (
                    <Descriptions style={{ marginTop: 16 }} column={1} size="small">
                        <Descriptions.Item label="Winning vendor">
                            {bid.award.vendor.vendor_name} — {formatIdr(bid.award.vendor.price)}
                        </Descriptions.Item>
                        {bid.sap_po_id && (
                            <Descriptions.Item label="SAP PO ID">
                                <Tag color="green">{bid.sap_po_id}</Tag>
                            </Descriptions.Item>
                        )}
                    </Descriptions>
                )}
                <Space style={{ marginTop: 16 }}>
                    {can.award && !bid.award && (
                        <Button
                            type="primary"
                            onClick={() =>
                                router.post(`/tabulation-bids/${bid.id}/award`, {
                                    tabulation_bid_vendor_id: lowestVendor?.id,
                                })
                            }
                        >
                            Award Lowest
                        </Button>
                    )}
                    {can.createPo && (
                        <Button type="primary" onClick={confirmCreatePo}>
                            Create PO
                        </Button>
                    )}
                </Space>
                {can.review && (
                    <Typography.Paragraph type="secondary" style={{ marginTop: 8 }}>
                        Procurement manager review mode is active.
                    </Typography.Paragraph>
                )}
            </Card>
        </AppLayout>
    );
}
