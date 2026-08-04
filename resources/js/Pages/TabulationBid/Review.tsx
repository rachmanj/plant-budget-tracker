import { Head, router } from '@inertiajs/react';
import { Button, Card } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import VendorComparisonTable from '@/Components/VendorComparisonTable';

interface Props {
    bid: {
        id: number;
        bid_no: string;
        status: string;
        vendors: Array<{ id: number; vendor_code: string; vendor_name: string; price: string; rank: number; stock_availability: string }>;
        award?: { tabulation_bid_vendor_id: number };
    };
}

export default function Review({ bid }: Props) {
    return (
        <AppLayout title={`Review ${bid.bid_no}`}>
            <Head title={bid.bid_no} />
            <Card title={`Tabulation Bid ${bid.bid_no}`}>
                <VendorComparisonTable vendors={bid.vendors} />
                {bid.status === 'forwarded_admin' && !bid.award && (
                    <Button
                        type="primary"
                        style={{ marginTop: 16 }}
                        onClick={() => router.post(`/tabulation-bids/${bid.id}/award`, {
                            tabulation_bid_vendor_id: bid.vendors[0]?.id,
                        })}
                    >
                        Award Lowest
                    </Button>
                )}
            </Card>
        </AppLayout>
    );
}
