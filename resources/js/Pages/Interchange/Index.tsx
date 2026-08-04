import { Head } from '@inertiajs/react';
import { Card, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function Index({ maps }: { maps: { data: Array<{ id: number; genuine_part_number: string; oem_part_number: string; sap_synced: boolean }> } }) {
    return (
        <AppLayout title="Interchange">
            <Head title="Interchange" />
            <Card title="Interchange Maps">
                <Table
                    rowKey="id"
                    dataSource={maps.data}
                    columns={[
                        { title: 'Genuine P/N', dataIndex: 'genuine_part_number' },
                        { title: 'OEM P/N', dataIndex: 'oem_part_number' },
                        { title: 'SAP', dataIndex: 'sap_synced', render: (s: boolean) => <Tag color={s ? 'green' : 'default'}>{s ? 'Synced' : 'Pending'}</Tag> },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
