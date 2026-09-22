import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, Card, Descriptions, Form, Input, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';

interface OverbudgetRow {
    id: number;
    request_no: string;
    plant_request_id: number | null;
    requested_amount: string;
    over_pct: string;
    status: string;
}

interface Prefill {
    plant_request_id?: number | string;
    budget_allocation_id?: number | string;
    requested_amount?: number | string;
    over_pct?: number | string;
}

interface PageProps {
    auth: { can: string[] };
}

interface Props {
    requests?: { data: OverbudgetRow[] };
    showForm?: boolean;
    prefill?: Prefill;
}

export default function Index({ requests = { data: [] }, showForm = false, prefill = {} }: Props) {
    const { auth } = usePage<PageProps>().props;
    const can = auth.can ?? [];
    const [form] = Form.useForm();

    const columns: ColumnsType<OverbudgetRow> = [
        { title: 'Request No', dataIndex: 'request_no' },
        { title: 'Plant Request', dataIndex: 'plant_request_id' },
        {
            title: 'Jumlah',
            dataIndex: 'requested_amount',
            render: (value: string) => formatIdr(value),
        },
        {
            title: 'Over %',
            dataIndex: 'over_pct',
            render: (value: string) => `${parseFloat(value).toFixed(1)}%`,
        },
        {
            title: 'Status',
            dataIndex: 'status',
            render: (status: string) => <Tag>{status}</Tag>,
        },
    ];

    const submitOverbudget = (values: { justification: string }) => {
        router.post('/overbudget', {
            budget_allocation_id: prefill.budget_allocation_id,
            plant_request_id: prefill.plant_request_id,
            requested_amount: prefill.requested_amount,
            over_pct: prefill.over_pct,
            justification: values.justification,
        });
    };

    return (
        <AppLayout title="Overbudget">
            <Head title="Overbudget" />
            {showForm && (
                <Card title="Ajukan Overbudget" style={{ marginBottom: 16 }}>
                    <Descriptions column={2} size="small" style={{ marginBottom: 16 }}>
                        <Descriptions.Item label="Plant Request ID">
                            {prefill.plant_request_id ?? '—'}
                        </Descriptions.Item>
                        <Descriptions.Item label="Allocation ID">
                            {prefill.budget_allocation_id ?? '—'}
                        </Descriptions.Item>
                        <Descriptions.Item label="Jumlah diminta">
                            {prefill.requested_amount != null
                                ? formatIdr(prefill.requested_amount)
                                : '—'}
                        </Descriptions.Item>
                        <Descriptions.Item label="Over %">
                            {prefill.over_pct != null
                                ? `${parseFloat(String(prefill.over_pct)).toFixed(1)}%`
                                : '—'}
                        </Descriptions.Item>
                    </Descriptions>
                    <Form
                        form={form}
                        layout="vertical"
                        onFinish={submitOverbudget}
                    >
                        <Form.Item
                            name="justification"
                            label="Justifikasi"
                            rules={[
                                { required: true, message: 'Justifikasi wajib diisi' },
                                { min: 10, message: 'Minimal 10 karakter' },
                            ]}
                        >
                            <Input.TextArea rows={4} placeholder="Alasan permintaan overbudget..." />
                        </Form.Item>
                        <Button type="primary" htmlType="submit">
                            Ajukan Overbudget
                        </Button>
                    </Form>
                </Card>
            )}
            <Card
                title="Overbudget Requests"
                extra={
                    can.includes('plant_request.create') ? (
                        <Link href="/overbudget/create">
                            <Button type="primary">Ajukan Overbudget Baru</Button>
                        </Link>
                    ) : null
                }
            >
                <Table rowKey="id" dataSource={requests.data} columns={columns} />
            </Card>
        </AppLayout>
    );
}
