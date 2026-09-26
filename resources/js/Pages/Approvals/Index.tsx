import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, Card, Input, Modal, Radio, Table, Tag, Tooltip, Typography } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';
import { roleLabel } from '@/utils/labels';

interface ApprovalDocument {
    type_label: string;
    document_no: string;
    project_code: string | null;
    project_name: string | null;
    amount: string | null;
    submitted_at: string | null;
    url: string;
    step_order: number;
    total_steps: number;
    step_label: string;
    is_director_threshold_step: boolean;
    director_threshold: string | null;
}

interface Approval {
    id: number;
    required_role: string;
    approvable_type: string;
    approvable_id: number;
    document: ApprovalDocument;
}

interface Props {
    approvals: { data: Approval[] };
}

interface NavPageProps {
    nav?: { roleLabels?: Record<string, string> };
}

export default function Index({ approvals }: Props) {
    const { nav } = usePage<NavPageProps>().props;
    const [selected, setSelected] = useState<Approval | null>(null);
    const [decision, setDecision] = useState('approved');
    const [remarks, setRemarks] = useState('');
    const [remarksError, setRemarksError] = useState<string | null>(null);

    const openModal = (row: Approval) => {
        setSelected(row);
        setDecision('approved');
        setRemarks('');
        setRemarksError(null);
    };

    const submit = () => {
        if (!selected) return;
        if ((decision === 'rejected' || decision === 'returned') && remarks.trim() === '') {
            setRemarksError('Remarks are required when rejecting or returning.');
            return;
        }
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
                        {
                            title: 'Document',
                            dataIndex: ['document', 'type_label'],
                            render: (_: string, row: Approval) => (
                                <div>
                                    <Typography.Text strong>{row.document.type_label}</Typography.Text>
                                    {row.document.is_director_threshold_step && (
                                        <div>
                                            <Tag color="gold">President Director threshold</Tag>
                                        </div>
                                    )}
                                </div>
                            ),
                        },
                        {
                            title: 'Number',
                            dataIndex: ['document', 'document_no'],
                            render: (no: string, row: Approval) => (
                                <Link href={row.document.url}>{no}</Link>
                            ),
                        },
                        {
                            title: 'Project',
                            render: (_: unknown, row: Approval) =>
                                row.document.project_name ??
                                row.document.project_code ??
                                '—',
                        },
                        {
                            title: 'Amount',
                            dataIndex: ['document', 'amount'],
                            render: (amount: string | null) =>
                                amount ? (
                                    <Tooltip title={formatIdr(amount)}>
                                        <span>{formatIdrCompact(amount)}</span>
                                    </Tooltip>
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            title: 'Step',
                            dataIndex: ['document', 'step_label'],
                        },
                        {
                            title: 'Role',
                            dataIndex: 'required_role',
                            render: (r: string) => <Tag>{roleLabel(r, nav?.roleLabels)}</Tag>,
                        },
                        {
                            title: 'Submitted',
                            dataIndex: ['document', 'submitted_at'],
                            render: (at: string | null) =>
                                at ? dayjs(at).format('D MMM YYYY HH:mm') : '—',
                        },
                        {
                            title: 'Action',
                            render: (_: unknown, row: Approval) => (
                                <Button size="small" onClick={() => openModal(row)}>
                                    Decide
                                </Button>
                            ),
                        },
                    ]}
                    pagination={false}
                />
            </Card>
            <Modal open={!!selected} onCancel={() => setSelected(null)} onOk={submit} title="Approval Decision">
                {selected?.document.is_director_threshold_step && selected.document.amount && (
                    <Typography.Paragraph type="warning">
                        Winning bid amount {formatIdr(selected.document.amount)} exceeds the President Director
                        threshold of {formatIdr(selected.document.director_threshold ?? '0')}.
                    </Typography.Paragraph>
                )}
                <Radio.Group value={decision} onChange={(e) => setDecision(e.target.value)}>
                    <Radio value="approved">Approve</Radio>
                    <Radio value="rejected">Reject</Radio>
                    <Radio value="returned">Return</Radio>
                </Radio.Group>
                <Input.TextArea
                    style={{ marginTop: 12 }}
                    rows={3}
                    value={remarks}
                    onChange={(e) => {
                        setRemarks(e.target.value);
                        setRemarksError(null);
                    }}
                    placeholder="Remarks"
                    status={remarksError ? 'error' : undefined}
                />
                {remarksError && (
                    <Typography.Text type="danger" style={{ display: 'block', marginTop: 4 }}>
                        {remarksError}
                    </Typography.Text>
                )}
            </Modal>
        </AppLayout>
    );
}
