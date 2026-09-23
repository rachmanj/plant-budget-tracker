import { Head, Link, router } from '@inertiajs/react';
import { Alert, Button, Card, DatePicker, Descriptions, Form, Input, Modal, Select, Table, Tag, Typography } from 'antd';
import { formatIdr } from '@/hooks/useCurrency';
import dayjs, { Dayjs } from 'dayjs';
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
        sap_pr_no?: string | null;
        sap_po_id?: string | null;
        sap_grpo_no?: string | null;
        received_at?: string | null;
        receiver?: { name: string } | null;
        lines: Array<{ part_number: string; material_name: string; qty: number; line_total: string }>;
        approvals: Array<{ step_order: number; required_role: string; decision: string }>;
    };
    tolerance: {
        projected_pct: string;
        within_tolerance: boolean;
        cap: string;
        base: string;
        projected: string;
        remaining: string;
        utilization_pct: string;
        committed_amount: string;
        actual_amount: string;
        message?: string;
    };
    procurement: {
        sap_po_id?: string | null;
        sap_pr_created_at?: string | null;
        sap_pr_sync_status?: string | null;
        sap_pr_sync_error?: string | null;
    };
    can: { submit: boolean; cancel: boolean; update?: boolean; receive?: boolean; createPr?: boolean };
}

function formatDate(value?: string | null): string {
    return value ? dayjs(value).format('DD-MM-YYYY') : '—';
}

export default function Show({
    request,
    tolerance,
    procurement = {},
    can = { submit: false, cancel: false },
}: Props) {
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelForm] = Form.useForm<{ po_stage: string; reason: string }>();
    const [receiveOpen, setReceiveOpen] = useState(false);
    const [receiveForm] = Form.useForm<{ sap_grpo_no: string; received_at: Dayjs; note?: string }>();

    const openCancelModal = () => {
        cancelForm.setFieldsValue({ po_stage: 'created', reason: '' });
        setCancelOpen(true);
    };

    const submitCancellation = (values: { po_stage: string; reason: string }) => {
        router.post(`/plant-requests/${request.id}/cancel`, values, {
            onSuccess: () => setCancelOpen(false),
        });
    };

    const openReceiveModal = () => {
        receiveForm.setFieldsValue({ sap_grpo_no: '', received_at: dayjs(), note: '' });
        setReceiveOpen(true);
    };

    const submitReceive = (values: { sap_grpo_no: string; received_at: Dayjs; note?: string }) => {
        router.post(
            `/plant-requests/${request.id}/receive`,
            {
                sap_grpo_no: values.sap_grpo_no,
                received_at: values.received_at.format('YYYY-MM-DD'),
                note: values.note,
            },
            { onSuccess: () => setReceiveOpen(false) }
        );
    };

    const createPr = () => {
        router.post(`/plant-requests/${request.id}/create-pr`);
    };

    return (
        <AppLayout title={request.request_no}>
            <Head title={request.request_no} />
            <Card title={request.request_no}>
                <LifecycleStepper status={request.status} sapPrNo={request.sap_pr_no} sapPoId={procurement.sap_po_id ?? request.sap_po_id} />
                <Descriptions style={{ marginTop: 16 }} column={2}>
                    <Descriptions.Item label="Unit">{request.unit_code_cache}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag>{request.status}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="SAP MR">{request.sap_mr_id}</Descriptions.Item>
                    <Descriptions.Item label="Total Est.">{formatIdr(request.estimated_total)}</Descriptions.Item>
                    <Descriptions.Item label="Sisa pagu proyek">
                        {formatIdr(tolerance.remaining)}
                        <Typography.Text type="secondary" style={{ marginLeft: 8 }}>
                            ({tolerance.utilization_pct}% terpakai sebelum permintaan ini)
                        </Typography.Text>
                    </Descriptions.Item>
                </Descriptions>
                <Typography.Text type="secondary" style={{ display: 'block', marginTop: 8 }}>
                    Proyeksi penggunaan pagu proyek setelah permintaan ini
                </Typography.Text>
                <BudgetProgressBar
                    utilizationPct={tolerance.projected_pct}
                    cap={tolerance.cap}
                    pagu={tolerance.base}
                    committed={tolerance.committed_amount}
                    actual={tolerance.actual_amount}
                    additionalAmount={request.estimated_total}
                    remaining={tolerance.remaining}
                />
                {!tolerance.within_tolerance && tolerance.message && (
                    <Alert type="warning" showIcon message={tolerance.message} style={{ marginTop: 12 }} />
                )}
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
                    {can.update && (
                        <Link href={`/plant-requests/${request.id}/edit`}>
                            <Button>Ubah Draft</Button>
                        </Link>
                    )}
                    {can.submit && (
                        <Button
                            type="primary"
                            onClick={() => router.post(`/plant-requests/${request.id}/submit`)}
                        >
                            Submit
                        </Button>
                    )}
                    {can.createPr && (
                        <Button onClick={createPr}>Buat PR di SAP</Button>
                    )}
                    {can.receive && (
                        <Button type="primary" onClick={openReceiveModal}>
                            Tandai Barang Diterima
                        </Button>
                    )}
                    {can.cancel && (
                        <Button danger onClick={openCancelModal}>
                            Ajukan Pembatalan
                        </Button>
                    )}
                </div>
            </Card>

            <Card title="Riwayat Pengadaan" style={{ marginTop: 16 }}>
                <Descriptions column={2} bordered size="small">
                    <Descriptions.Item label="Nomor MR">{request.sap_mr_id ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Nomor PR">
                        {request.sap_pr_no
                            ? `${request.sap_pr_no}${procurement.sap_pr_created_at ? ` (${formatDate(procurement.sap_pr_created_at)})` : ''}`
                            : '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Nomor PO">
                        {procurement.sap_po_id ?? request.sap_po_id ?? '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Nomor GRPO">{request.sap_grpo_no ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Tanggal Terima">{formatDate(request.received_at)}</Descriptions.Item>
                    <Descriptions.Item label="Diterima Oleh">{request.receiver?.name ?? '—'}</Descriptions.Item>
                </Descriptions>
                {procurement.sap_pr_sync_status === 'failed' && (
                    <Tag color="error" style={{ marginTop: 12 }}>
                        Sinkronisasi PR ke SAP gagal: {procurement.sap_pr_sync_error ?? 'Alasan tidak diketahui'}
                    </Tag>
                )}
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

            <Modal
                title="Tandai Barang Diterima"
                open={receiveOpen}
                onCancel={() => setReceiveOpen(false)}
                onOk={() => receiveForm.submit()}
                okText="Simpan"
                cancelText="Batal"
            >
                <Form form={receiveForm} layout="vertical" onFinish={submitReceive}>
                    <Form.Item
                        name="sap_grpo_no"
                        label="Nomor GRPO"
                        rules={[{ required: true, message: 'Nomor GRPO wajib diisi' }]}
                    >
                        <Input placeholder="Contoh: GRPO-1023" />
                    </Form.Item>
                    <Form.Item
                        name="received_at"
                        label="Tanggal Terima"
                        rules={[{ required: true, message: 'Tanggal terima wajib diisi' }]}
                    >
                        <DatePicker style={{ width: '100%' }} format="DD-MM-YYYY" disabledDate={(d) => d.isAfter(dayjs(), 'day')} />
                    </Form.Item>
                    <Form.Item name="note" label="Catatan (opsional)">
                        <Input.TextArea rows={3} placeholder="Catatan tambahan mengenai penerimaan barang" />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
