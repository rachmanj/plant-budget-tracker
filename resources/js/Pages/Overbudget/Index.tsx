import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, Card, Descriptions, Form, Input, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';
import { statusLabel } from '@/utils/labels';

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
            title: 'Amount',
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
            render: (status: string) => <Tag>{statusLabel(status)}</Tag>,
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
                <Card title="Submit Overbudget Request" style={{ marginBottom: 16 }}>
                    <Descriptions column={2} size="small" style={{ marginBottom: 16 }}>
                        <Descriptions.Item label="Plant Request ID">
                            {prefill.plant_request_id ?? '—'}
                        </Descriptions.Item>
                        <Descriptions.Item label="Allocation ID">
                            {prefill.budget_allocation_id ?? '—'}
                        </Descriptions.Item>
                        <Descriptions.Item label="Requested amount">
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
                            label="Justification"
                            rules={[
                                { required: true, message: 'Justification is required' },
                                { min: 10, message: 'At least 10 characters' },
                            ]}
                        >
                            <Input.TextArea rows={4} placeholder="Reason for overbudget request..." />
                        </Form.Item>
                        <Button type="primary" htmlType="submit">
                            Submit Overbudget
                        </Button>
                    </Form>
                </Card>
            )}
            <Card
                title="Overbudget Requests"
                extra={
                    can.includes('plant_request.create') ? (
                        <Link href="/overbudget/create">
                            <Button type="primary">New Overbudget Request</Button>
                        </Link>
                    ) : null
                }
            >
                <Table rowKey="id" dataSource={requests.data} columns={columns} />
            </Card>
        </AppLayout>
    );
}
