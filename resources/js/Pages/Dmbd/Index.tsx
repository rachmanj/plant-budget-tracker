import { Head, router } from '@inertiajs/react';
import { Card, Select, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    equipment: Array<{ id: number; unit_code: string }>;
    entries: Record<number, { operational_status: string }>;
    reportDate: string;
}

const statusColor: Record<string, string> = { rfu: 'green', standby: 'gold', breakdown: 'red' };

export default function Index({ equipment, entries, reportDate }: Props) {
    const updateStatus = (equipmentId: number, unitCode: string, status: string) => {
        router.post('/dmbd', {
            equipment_id: equipmentId,
            unit_code_cache: unitCode,
            operational_status: status,
        });
    };

    return (
        <AppLayout title="DMBD">
            <Head title="DMBD" />
            <Card title={`Daily Monitoring — ${reportDate}`}>
                <Table
                    rowKey="id"
                    dataSource={equipment}
                    columns={[
                        { title: 'Unit', dataIndex: 'unit_code' },
                        {
                            title: 'Status',
                            render: (_: unknown, row: { id: number; unit_code: string }) => {
                                const current = entries[row.id]?.operational_status ?? 'rfu';
                                return (
                                    <Select
                                        value={current}
                                        style={{ width: 140 }}
                                        onChange={(v) => updateStatus(row.id, row.unit_code, v)}
                                        options={[
                                            { value: 'rfu', label: <Tag color="green">RFU</Tag> },
                                            { value: 'standby', label: <Tag color="gold">Standby</Tag> },
                                            { value: 'breakdown', label: <Tag color="red">Breakdown</Tag> },
                                        ]}
                                    />
                                );
                            },
                        },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
