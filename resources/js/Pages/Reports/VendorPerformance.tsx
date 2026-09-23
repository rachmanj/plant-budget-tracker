import { Head } from '@inertiajs/react';
import { Card, Table } from 'antd';
import ReportExportButtons from '@/Components/ReportExportButtons';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    data?: unknown[];
    can?: { export?: boolean };
}

export default function VendorPerformance({ data = [], can = {} }: Props) {
    return (
        <AppLayout title="Vendor Performance">
            <Head title="Vendor Performance" />
            <Card title="Vendor Performance">
                <ReportExportButtons
                    reportType="vendor-performance"
                    canExport={can.export === true}
                />
                <Table
                    rowKey="vendor_code"
                    dataSource={data as never[]}
                    columns={[
                        { title: 'Vendor', dataIndex: 'vendor_name' },
                        { title: 'Indent %', dataIndex: 'indent_pct' },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
