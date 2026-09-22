import { Head, router } from '@inertiajs/react';
import { Button, Card, Descriptions, Form, Input, Modal, Select, Table, Tag } from 'antd';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import LifecycleStepper from '@/Components/LifecycleStepper';
import BudgetProgressBar from '@/Components/BudgetProgressBar';

interface Props {
    request: {
        id: number;
        request_no: string;
        status: string;
        unit_code_cache: string;
        estimated_total: string;
        sap_mr_id: number;
        sap_pr_no?: string;
        lines: Array<{ part_number: string; material_name: string; qty: number; line_total: string }>;
        approvals: Array<{ step_order: number; required_role: string; decision: string }>;
    };
    tolerance: { projected_pct: string; within_tolerance: boolean; cap: string };
    can: { submit: boolean; cancel: boolean };
}

export default function Show({ request, tolerance, can = { submit: false, cancel: false } }: Props) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelForm] = Form.useForm<{ po_stage: string; reason: string }>();

    const openCancelModal = () => {
        cancelForm.setFieldsValue({ po_stage: 'created', reason: '' });
        setCancelOpen(true);
    };

    const submitCancellation = (values: { po_stage: string; reason: string }) => {
        router.post(`/plant-requests/${request.id}/cancel`, values, {
            onSuccess: () => setCancelOpen(false),
        });
    };

    return (
        <AppLayout title={request.request_no}>
            <Head title={request.request_no} />
            <Card title={request.request_no}>
                <LifecycleStepper status={request.status} sapPrNo={request.sap_pr_no} />
                <Descriptions style={{ marginTop: 16 }} column={2}>
                    <Descriptions.Item label="Unit">{request.unit_code_cache}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag>{request.status}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="SAP MR">{request.sap_mr_id}</Descriptions.Item>
                    <Descriptions.Item label="Total Est.">{request.estimated_total}</Descriptions.Item>
                </Descriptions>
                <BudgetProgressBar
                    utilizationPct={parseFloat(tolerance.projected_pct)}
                    capPct={110}
                />
                <Table
                    style={{ marginTop: 16 }}
                    rowKey="part_number"
                    dataSource={request.lines}
                    columns={[
                        { title: 'P/N', dataIndex: 'part_number' },
                        { title: 'Material', dataIndex: 'material_name' },
                        { title: 'Qty', dataIndex: 'qty' },
                        { title: 'Total', dataIndex: 'line_total' },
                    ]}
                    pagination={false}
                />
                <div style={{ marginTop: 16, display: 'flex', gap: 8 }}>
                    {can.submit && (
                        <Button
                            type="primary"
                            onClick={() => router.post(`/plant-requests/${request.id}/submit`)}
                        >
                            Submit
                        </Button>
                    )}
                    {can.cancel && (
                        <Button danger onClick={openCancelModal}>
                            Ajukan Pembatalan
                        </Button>
                    )}
                </div>
            </Card>
            <Modal
                title="Ajukan Pembatalan"
                open={cancelOpen}
                onCancel={() => setCancelOpen(false)}
                onOk={() => cancelForm.submit()}
                okText="Ajukan"
                cancelText="Batal"
            >
                <Form form={cancelForm} layout="vertical" onFinish={submitCancellation}>
                    <Form.Item
                        name="po_stage"
                        label="Tahap PO"
                        rules={[{ required: true, message: 'Pilih tahap PO' }]}
                    >
                        <Select
                            options={[
                                { value: 'created', label: 'Created' },
                                { value: 'approved', label: 'Approved' },
                                { value: 'sent', label: 'Sent' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item
                        name="reason"
                        label="Alasan"
                        rules={[{ required: true, message: 'Alasan wajib diisi' }]}
                    >
                        <Input.TextArea rows={3} />
                    </Form.Item>
                    <Tag color="warning">
                        Plant tidak dapat membatalkan setelah PO berstatus Sent — permintaan akan ditolak
                        server.
                    </Tag>
                </Form>
            </Modal>
        </AppLayout>
    );
}
