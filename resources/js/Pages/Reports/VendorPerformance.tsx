import { Head } from '@inertiajs/react';
import { Table } from 'antd';
import ReportExportButtons from '@/Components/ReportExportButtons';
import AppLayout from '@/Layouts/AppLayout';
import { ReportPage } from '@/Pages/Reports/reportShell';

interface Props {
    data?: unknown[];
    can?: { export?: boolean };
}

export default function VendorPerformance({ data = [], can = {} }: Props) {
    const rows = Array.isArray(data) ? data : [];
    const subtitle = rows.length > 0 ? `${rows.length} vendors` : undefined;

    return (
        <AppLayout title="Vendor Performance">
            <Head title="Vendor Performance" />
            <ReportPage title="Vendor Performance" subtitle={subtitle}>
                <ReportExportButtons reportType="vendor-performance" canExport={can.export === true} />
                <Table
                    style={{ marginTop: 16 }}
                    rowKey="vendor_code"
                    dataSource={data as never[]}
                    columns={[
                        { title: 'Vendor', dataIndex: 'vendor_name' },
                        { title: 'Indent %', dataIndex: 'indent_pct' },
                    ]}
                />
            </ReportPage>
        </AppLayout>
    );
}