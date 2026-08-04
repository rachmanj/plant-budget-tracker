import { Head, router } from '@inertiajs/react';
import { Button, Card, Input, Modal, Radio, Table, Tag } from 'antd';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

interface Approval {
    id: number;
    required_role: string;
    approvable_type: string;
    approvable_id: number;
}

interface Props {
    approvals: { data: Approval[] };
}

export default function Index({ approvals }: Props) {
    const [selected, setSelected] = useState<Approval | null>(null);
    const [decision, setDecision] = useState('approved');
    const [remarks, setRemarks] = useState('');

    const submit = () => {
        if (!selected) return;
        router.post(`/approvals/${selected.id}/decide`, { decision, remarks });
        setSelected(null);
    };

    return (
        <AppLayout title="My Approvals">
            <Head title="My Approvals" />
            <Card title="Pending Approvals">
                <Table
                    rowKey="id"
                    dataSource={approvals.data}
                    columns={[
                        { title: 'Type', dataIndex: 'approvable_type', render: (t: string) => t.split('\\').pop() },
                        { title: 'ID', dataIndex: 'approvable_id' },
                        { title: 'Role', dataIndex: 'required_role', render: (r: string) => <Tag>{r}</Tag> },
                        {
                            title: 'Action',
                            render: (_: unknown, row: Approval) => (
                                <Button size="small" onClick={() => setSelected(row)}>
                                    Decide
                                </Button>
                            ),
                        },
                    ]}
                    pagination={false}
                />
            </Card>
            <Modal open={!!selected} onCancel={() => setSelected(null)} onOk={submit} title="Approval Decision">
                <Radio.Group value={decision} onChange={(e) => setDecision(e.target.value)}>
                    <Radio value="approved">Approve</Radio>
                    <Radio value="rejected">Reject</Radio>
                    <Radio value="returned">Return</Radio>
                </Radio.Group>
                <Input.TextArea
                    style={{ marginTop: 12 }}
                    rows={3}
                    value={remarks}
                    onChange={(e) => setRemarks(e.target.value)}
                    placeholder="Remarks"
                />
            </Modal>
        </AppLayout>
    );
}
