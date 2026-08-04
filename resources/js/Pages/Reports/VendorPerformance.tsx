import { Head } from '@inertiajs/react';
import { Card, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function VendorPerformance({ data = [] }: { data?: unknown[] }) {
    return (
        <AppLayout title="Vendor Performance">
            <Head title="Vendor Performance" />
            <Card title="Vendor Performance">
                <Table rowKey="vendor_code" dataSource={data as never[]} columns={[
                    { title: 'Vendor', dataIndex: 'vendor_name' },
                    { title: 'Indent %', dataIndex: 'indent_pct' },
                ]} />
            </Card>
        </AppLayout>
    );
}
