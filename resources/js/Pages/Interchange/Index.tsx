import { Head, router, useForm } from '@inertiajs/react';
import { Button, Card, Form, Input, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import AppLayout from '@/Layouts/AppLayout';

interface MapRow {
    id: number;
    genuine_part_number: string;
    oem_part_number: string;
    material_name: string;
    sap_synced: boolean;
    technical_signoff_by: number | null;
    signoff_by?: { name: string } | null;
    can: { signoff: boolean };
}

interface Props {
    maps: { data: MapRow[] };
    can: { create: boolean };
}

export default function Index({ maps, can = { create: false } }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        genuine_part_number: '',
        oem_part_number: '',
        material_name: '',
    });

    const columns: ColumnsType<MapRow> = [
        { title: 'Genuine P/N', dataIndex: 'genuine_part_number' },
        { title: 'OEM P/N', dataIndex: 'oem_part_number' },
        { title: 'Material Name', dataIndex: 'material_name' },
        {
            title: 'SAP',
            dataIndex: 'sap_synced',
            render: (s: boolean) => (
                <Tag color={s ? 'green' : 'default'}>{s ? 'Synced' : 'Pending'}</Tag>
            ),
        },
        {
            title: 'Signed off by',
            key: 'signoff',
            render: (_, row) => row.signoff_by?.name ?? '—',
        },
        {
            title: 'Actions',
            key: 'actions',
            render: (_, row) =>
                row.can.signoff ? (
                    <Button
                        type="primary"
                        size="small"
                        onClick={() => router.post(`/interchange/${row.id}/signoff`)}
                    >
                        Technical Sign-off
                    </Button>
                ) : null,
        },
    ];

    const submitMapping = () => {
        post('/interchange', { onSuccess: () => reset() });
    };

    return (
        <AppLayout title="Interchange">
            <Head title="Interchange" />
            {can.create && (
                <Card title="Add Mapping" style={{ marginBottom: 16 }}>
                    <Form layout="vertical" onFinish={submitMapping}>
                        <Form.Item
                            label="Genuine P/N"
                            required
                            validateStatus={errors.genuine_part_number ? 'error' : undefined}
                            help={errors.genuine_part_number}
                        >
                            <Input
                                value={data.genuine_part_number}
                                onChange={(e) => setData('genuine_part_number', e.target.value)}
                            />
                        </Form.Item>
                        <Form.Item
                            label="OEM P/N"
                            required
                            validateStatus={errors.oem_part_number ? 'error' : undefined}
                            help={errors.oem_part_number}
                        >
                            <Input
                                value={data.oem_part_number}
                                onChange={(e) => setData('oem_part_number', e.target.value)}
                            />
                        </Form.Item>
                        <Form.Item
                            label="Material Name"
                            required
                            validateStatus={errors.material_name ? 'error' : undefined}
                            help={errors.material_name}
                        >
                            <Input
                                value={data.material_name}
                                onChange={(e) => setData('material_name', e.target.value)}
                            />
                        </Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Add Mapping
                        </Button>
                    </Form>
                </Card>
            )}
            <Card title="Interchange Maps">
                <Table rowKey="id" dataSource={maps.data} columns={columns} />
            </Card>
        </AppLayout>
    );
}
